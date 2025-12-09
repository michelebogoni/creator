/**
 * @fileoverview Unit tests for Rate Limit Middleware
 * @module tests/unit/middleware/rateLimit.test
 */

import { Request, Response } from 'express';
import {
  checkRateLimit,
  sendRateLimitResponse,
  createRateLimitMiddleware,
  getClientIP,
} from '../../../src/middleware/rateLimit';
import { Logger } from '../../../src/lib/logger';
import * as firestore from '../../../src/lib/firestore';

// Mock dependencies
jest.mock('../../../src/lib/firestore', () => ({
  checkAndIncrementRateLimit: jest.fn(),
}));

jest.mock('../../../src/lib/logger', () => ({
  Logger: jest.fn().mockImplementation(() => ({
    info: jest.fn(),
    warn: jest.fn(),
    error: jest.fn(),
    debug: jest.fn(),
    child: jest.fn().mockReturnThis(),
  })),
}));

describe('Rate Limit Middleware', () => {
  let mockRequest: Partial<Request>;
  let mockResponse: Partial<Response>;
  let mockLogger: Logger;
  let mockJson: jest.Mock;
  let mockStatus: jest.Mock;
  let mockSetHeader: jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();

    mockJson = jest.fn().mockReturnThis();
    mockSetHeader = jest.fn().mockReturnThis();
    mockStatus = jest.fn().mockReturnValue({ json: mockJson });

    mockRequest = {
      headers: {},
      socket: { remoteAddress: '192.168.1.1' } as unknown as Request['socket'],
      ip: '192.168.1.1',
    };

    mockResponse = {
      status: mockStatus,
      json: mockJson,
      setHeader: mockSetHeader,
    };

    mockLogger = new Logger();
  });

  describe('getClientIP', () => {
    it('should extract IP from X-Forwarded-For header', () => {
      mockRequest.headers = {
        'x-forwarded-for': '203.0.113.50, 70.41.3.18',
      };

      const ip = getClientIP(mockRequest as Request);
      expect(ip).toBe('203.0.113.50');
    });

    it('should extract IP from X-Real-IP header', () => {
      mockRequest.headers = {
        'x-real-ip': '203.0.113.75',
      };

      const ip = getClientIP(mockRequest as Request);
      expect(ip).toBe('203.0.113.75');
    });

    it('should fallback to socket remoteAddress', () => {
      mockRequest.headers = {};
      mockRequest.socket = { remoteAddress: '10.0.0.1' } as unknown as Request['socket'];

      const ip = getClientIP(mockRequest as Request);
      expect(ip).toBe('10.0.0.1');
    });

    it('should fallback to req.ip', () => {
      // Create a fresh request object with ip set
      const requestWithIp = {
        headers: {},
        socket: undefined,
        ip: '127.0.0.1',
      } as unknown as Request;

      const ip = getClientIP(requestWithIp);
      expect(ip).toBe('127.0.0.1');
    });
  });

  describe('checkRateLimit', () => {
    const rateLimitConfig = {
      maxRequests: 10,
      endpoint: 'test_endpoint',
    };

    it('should allow request when under rate limit threshold', async () => {
      // Arrange
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValue({
        limited: false,
        count: 5, // Under limit of 10
      });

      // Act
      const result = await checkRateLimit(
        mockRequest as Request,
        rateLimitConfig,
        mockLogger
      );

      // Assert
      expect(result.allowed).toBe(true);
      expect(result.count).toBe(5);
    });

    it('should deny request when over rate limit threshold', async () => {
      // Arrange
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValue({
        limited: true,
        count: 11, // Over limit of 10
      });

      // Act
      const result = await checkRateLimit(
        mockRequest as Request,
        rateLimitConfig,
        mockLogger
      );

      // Assert
      expect(result.allowed).toBe(false);
      expect(result.count).toBe(11);
    });

    it('should fail open when rate limit check throws error', async () => {
      // Arrange
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockRejectedValue(
        new Error('Firestore error')
      );

      // Act
      const result = await checkRateLimit(
        mockRequest as Request,
        rateLimitConfig,
        mockLogger
      );

      // Assert - Fail open: allow request
      expect(result.allowed).toBe(true);
      expect(result.count).toBe(0);
    });
  });

  describe('sendRateLimitResponse', () => {
    it('should send 429 status with rate limit headers', () => {
      // Act
      sendRateLimitResponse(mockResponse as Response, 60);

      // Assert
      expect(mockSetHeader).toHaveBeenCalledWith('Retry-After', '60');
      expect(mockSetHeader).toHaveBeenCalledWith(
        'X-RateLimit-Reset',
        expect.any(String)
      );
      expect(mockStatus).toHaveBeenCalledWith(429);
      expect(mockJson).toHaveBeenCalledWith({
        success: false,
        error: expect.any(String),
        code: 'RATE_LIMITED',
      });
    });

    it('should default retry after to 60 seconds', () => {
      // Act
      sendRateLimitResponse(mockResponse as Response);

      // Assert
      expect(mockSetHeader).toHaveBeenCalledWith('Retry-After', '60');
    });
  });

  describe('createRateLimitMiddleware', () => {
    it('should return continue: true when under rate limit', async () => {
      // Arrange
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValue({
        limited: false,
        count: 3,
      });

      const middleware = createRateLimitMiddleware({
        maxRequests: 10,
        endpoint: 'test',
      });

      // Act
      const result = await middleware(
        mockRequest as Request,
        mockResponse as Response,
        mockLogger
      );

      // Assert
      expect(result.continue).toBe(true);
      expect(mockSetHeader).toHaveBeenCalledWith('X-RateLimit-Limit', '10');
      expect(mockSetHeader).toHaveBeenCalledWith('X-RateLimit-Remaining', '7');
    });

    it('should return continue: false and send 429 when over rate limit', async () => {
      // Arrange
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValue({
        limited: true,
        count: 15,
      });

      const middleware = createRateLimitMiddleware({
        maxRequests: 10,
        endpoint: 'test',
      });

      // Act
      const result = await middleware(
        mockRequest as Request,
        mockResponse as Response,
        mockLogger
      );

      // Assert
      expect(result.continue).toBe(false);
      expect(mockStatus).toHaveBeenCalledWith(429);
      expect(mockSetHeader).toHaveBeenCalledWith('X-RateLimit-Remaining', '0');
    });

    it('should use default config when not provided', async () => {
      // Arrange
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValue({
        limited: false,
        count: 1,
      });

      const middleware = createRateLimitMiddleware();

      // Act
      const result = await middleware(
        mockRequest as Request,
        mockResponse as Response,
        mockLogger
      );

      // Assert
      expect(result.continue).toBe(true);
      expect(firestore.checkAndIncrementRateLimit).toHaveBeenCalledWith(
        'default', // default endpoint
        expect.any(String),
        10 // default maxRequests
      );
    });
  });

  describe('rate limit window reset', () => {
    beforeEach(() => {
      jest.useFakeTimers();
    });

    afterEach(() => {
      jest.useRealTimers();
    });

    it('should allow requests after rate limit window resets', async () => {
      // Arrange
      const middleware = createRateLimitMiddleware({
        maxRequests: 2,
        endpoint: 'test',
      });

      // First request - allowed
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValueOnce({
        limited: false,
        count: 1,
      });

      let result = await middleware(
        mockRequest as Request,
        mockResponse as Response,
        mockLogger
      );
      expect(result.continue).toBe(true);

      // Second request - allowed
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValueOnce({
        limited: false,
        count: 2,
      });

      result = await middleware(
        mockRequest as Request,
        mockResponse as Response,
        mockLogger
      );
      expect(result.continue).toBe(true);

      // Third request - rate limited
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValueOnce({
        limited: true,
        count: 3,
      });

      result = await middleware(
        mockRequest as Request,
        mockResponse as Response,
        mockLogger
      );
      expect(result.continue).toBe(false);

      // Advance time by 61 seconds (past the minute window)
      jest.advanceTimersByTime(61000);

      // Fourth request after window reset - allowed (simulated by Firestore returning new window)
      (firestore.checkAndIncrementRateLimit as jest.Mock).mockResolvedValueOnce({
        limited: false,
        count: 1, // New minute window
      });

      result = await middleware(
        mockRequest as Request,
        mockResponse as Response,
        mockLogger
      );
      expect(result.continue).toBe(true);
    });
  });
});
