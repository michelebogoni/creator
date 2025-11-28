<?php
/**
 * File System Manager
 *
 * @package CreatorCore
 */

namespace CreatorCore\Development;

defined( 'ABSPATH' ) || exit;

use CreatorCore\Audit\AuditLogger;

/**
 * Class FileSystemManager
 *
 * Manages file system operations for WordPress development
 */
class FileSystemManager {

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private AuditLogger $logger;

    /**
     * WordPress filesystem instance
     *
     * @var \WP_Filesystem_Base|null
     */
    private ?\WP_Filesystem_Base $filesystem = null;

    /**
     * Allowed file extensions for reading/writing
     *
     * @var array
     */
    private array $allowed_extensions = [
        'php', 'js', 'css', 'html', 'htm', 'json', 'xml', 'txt', 'md',
        'scss', 'sass', 'less', 'ts', 'tsx', 'jsx', 'vue', 'svg',
        'htaccess', 'ini', 'yaml', 'yml', 'twig', 'blade.php',
    ];

    /**
     * Protected paths that cannot be modified
     *
     * @var array
     */
    private array $protected_paths = [
        'wp-admin',
        'wp-includes',
    ];

    /**
     * Constructor
     *
     * @param AuditLogger|null $logger Audit logger instance.
     */
    public function __construct( ?AuditLogger $logger = null ) {
        $this->logger = $logger ?? new AuditLogger();
        $this->init_filesystem();
    }

    /**
     * Initialize WordPress filesystem
     *
     * @return bool
     */
    private function init_filesystem(): bool {
        if ( $this->filesystem !== null ) {
            return true;
        }

        global $wp_filesystem;

        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $this->filesystem = $wp_filesystem;

        return $this->filesystem !== null;
    }

    /**
     * Read a file
     *
     * @param string $file_path Absolute or relative path to file.
     * @return array Result with success status and content.
     */
    public function read_file( string $file_path ): array {
        $absolute_path = $this->normalize_path( $file_path );

        if ( ! $this->is_safe_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Path is outside allowed directories', 'creator-core' ),
            ];
        }

        if ( ! $this->filesystem->exists( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'File not found', 'creator-core' ),
            ];
        }

        if ( $this->filesystem->is_dir( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Path is a directory, not a file', 'creator-core' ),
            ];
        }

        $content = $this->filesystem->get_contents( $absolute_path );

        if ( $content === false ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to read file', 'creator-core' ),
            ];
        }

        $this->logger->info( 'file_read', [
            'path' => $absolute_path,
            'size' => strlen( $content ),
        ]);

        return [
            'success'  => true,
            'content'  => $content,
            'path'     => $absolute_path,
            'size'     => strlen( $content ),
            'modified' => $this->filesystem->mtime( $absolute_path ),
        ];
    }

    /**
     * Write a file
     *
     * @param string $file_path Absolute or relative path to file.
     * @param string $content   File content.
     * @param bool   $backup    Create backup before writing.
     * @return array Result with success status.
     */
    public function write_file( string $file_path, string $content, bool $backup = true ): array {
        $absolute_path = $this->normalize_path( $file_path );

        if ( ! $this->is_safe_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Path is outside allowed directories', 'creator-core' ),
            ];
        }

        if ( $this->is_protected_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Cannot modify protected WordPress core files', 'creator-core' ),
            ];
        }

        // Create backup if file exists
        $backup_path = null;
        if ( $backup && $this->filesystem->exists( $absolute_path ) ) {
            $backup_result = $this->create_backup( $absolute_path );
            if ( $backup_result['success'] ) {
                $backup_path = $backup_result['backup_path'];
            }
        }

        // Ensure directory exists
        $directory = dirname( $absolute_path );
        if ( ! $this->filesystem->exists( $directory ) ) {
            if ( ! wp_mkdir_p( $directory ) ) {
                return [
                    'success' => false,
                    'error'   => __( 'Failed to create directory', 'creator-core' ),
                ];
            }
        }

        $result = $this->filesystem->put_contents( $absolute_path, $content, FS_CHMOD_FILE );

        if ( ! $result ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to write file', 'creator-core' ),
            ];
        }

        $this->logger->info( 'file_written', [
            'path'        => $absolute_path,
            'size'        => strlen( $content ),
            'backup_path' => $backup_path,
        ]);

        return [
            'success'     => true,
            'path'        => $absolute_path,
            'size'        => strlen( $content ),
            'backup_path' => $backup_path,
            'message'     => __( 'File written successfully', 'creator-core' ),
        ];
    }

    /**
     * Delete a file
     *
     * @param string $file_path Absolute or relative path to file.
     * @param bool   $backup    Create backup before deleting.
     * @return array Result with success status.
     */
    public function delete_file( string $file_path, bool $backup = true ): array {
        $absolute_path = $this->normalize_path( $file_path );

        if ( ! $this->is_safe_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Path is outside allowed directories', 'creator-core' ),
            ];
        }

        if ( $this->is_protected_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Cannot delete protected files', 'creator-core' ),
            ];
        }

        if ( ! $this->filesystem->exists( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'File not found', 'creator-core' ),
            ];
        }

        // Create backup
        $backup_path = null;
        if ( $backup ) {
            $backup_result = $this->create_backup( $absolute_path );
            if ( $backup_result['success'] ) {
                $backup_path = $backup_result['backup_path'];
            }
        }

        $result = $this->filesystem->delete( $absolute_path );

        if ( ! $result ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to delete file', 'creator-core' ),
            ];
        }

        $this->logger->info( 'file_deleted', [
            'path'        => $absolute_path,
            'backup_path' => $backup_path,
        ]);

        return [
            'success'     => true,
            'path'        => $absolute_path,
            'backup_path' => $backup_path,
            'message'     => __( 'File deleted successfully', 'creator-core' ),
        ];
    }

    /**
     * Create a directory
     *
     * @param string $dir_path Absolute or relative path to directory.
     * @return array Result with success status.
     */
    public function create_directory( string $dir_path ): array {
        $absolute_path = $this->normalize_path( $dir_path );

        if ( ! $this->is_safe_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Path is outside allowed directories', 'creator-core' ),
            ];
        }

        if ( $this->filesystem->exists( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Directory already exists', 'creator-core' ),
            ];
        }

        $result = wp_mkdir_p( $absolute_path );

        if ( ! $result ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to create directory', 'creator-core' ),
            ];
        }

        $this->logger->info( 'directory_created', [
            'path' => $absolute_path,
        ]);

        return [
            'success' => true,
            'path'    => $absolute_path,
            'message' => __( 'Directory created successfully', 'creator-core' ),
        ];
    }

    /**
     * List directory contents
     *
     * @param string $dir_path  Absolute or relative path to directory.
     * @param bool   $recursive Include subdirectories.
     * @return array Result with success status and file list.
     */
    public function list_directory( string $dir_path, bool $recursive = false ): array {
        $absolute_path = $this->normalize_path( $dir_path );

        if ( ! $this->is_safe_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Path is outside allowed directories', 'creator-core' ),
            ];
        }

        if ( ! $this->filesystem->exists( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Directory not found', 'creator-core' ),
            ];
        }

        if ( ! $this->filesystem->is_dir( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Path is not a directory', 'creator-core' ),
            ];
        }

        $files = $this->filesystem->dirlist( $absolute_path, false, $recursive );

        if ( $files === false ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to list directory', 'creator-core' ),
            ];
        }

        $formatted = [];
        foreach ( $files as $name => $info ) {
            $formatted[] = [
                'name'     => $name,
                'type'     => $info['type'] === 'd' ? 'directory' : 'file',
                'size'     => $info['size'] ?? 0,
                'modified' => $info['lastmodunix'] ?? 0,
                'path'     => trailingslashit( $absolute_path ) . $name,
            ];
        }

        return [
            'success' => true,
            'path'    => $absolute_path,
            'files'   => $formatted,
            'count'   => count( $formatted ),
        ];
    }

    /**
     * Search for files matching a pattern
     *
     * @param string $directory Directory to search in.
     * @param string $pattern   Glob pattern to match.
     * @param bool   $recursive Search recursively.
     * @return array Result with matched files.
     */
    public function find_files( string $directory, string $pattern, bool $recursive = true ): array {
        $absolute_path = $this->normalize_path( $directory );

        if ( ! $this->is_safe_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Path is outside allowed directories', 'creator-core' ),
            ];
        }

        if ( ! $this->filesystem->exists( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Directory not found', 'creator-core' ),
            ];
        }

        $flags = $recursive ? GLOB_BRACE : GLOB_BRACE | GLOB_NOSORT;
        $search_pattern = $recursive
            ? trailingslashit( $absolute_path ) . '**/' . $pattern
            : trailingslashit( $absolute_path ) . $pattern;

        // Use RecursiveDirectoryIterator for better recursive search
        $files = [];
        if ( $recursive ) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $absolute_path, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ( $iterator as $file ) {
                if ( $file->isFile() && fnmatch( $pattern, $file->getFilename() ) ) {
                    $files[] = [
                        'name'     => $file->getFilename(),
                        'path'     => $file->getPathname(),
                        'size'     => $file->getSize(),
                        'modified' => $file->getMTime(),
                    ];
                }
            }
        } else {
            $matches = glob( trailingslashit( $absolute_path ) . $pattern );
            foreach ( $matches as $match ) {
                if ( is_file( $match ) ) {
                    $files[] = [
                        'name'     => basename( $match ),
                        'path'     => $match,
                        'size'     => filesize( $match ),
                        'modified' => filemtime( $match ),
                    ];
                }
            }
        }

        return [
            'success'   => true,
            'directory' => $absolute_path,
            'pattern'   => $pattern,
            'files'     => $files,
            'count'     => count( $files ),
        ];
    }

    /**
     * Search for content within files
     *
     * @param string $directory   Directory to search in.
     * @param string $search_term Term to search for.
     * @param string $file_pattern File pattern to search in.
     * @return array Result with matches.
     */
    public function search_in_files( string $directory, string $search_term, string $file_pattern = '*.php' ): array {
        $find_result = $this->find_files( $directory, $file_pattern, true );

        if ( ! $find_result['success'] ) {
            return $find_result;
        }

        $matches = [];
        foreach ( $find_result['files'] as $file ) {
            $content = $this->filesystem->get_contents( $file['path'] );
            if ( $content === false ) {
                continue;
            }

            $lines = explode( "\n", $content );
            foreach ( $lines as $line_number => $line ) {
                if ( stripos( $line, $search_term ) !== false ) {
                    $matches[] = [
                        'file'        => $file['path'],
                        'line_number' => $line_number + 1,
                        'line'        => trim( $line ),
                        'context'     => $this->get_line_context( $lines, $line_number, 2 ),
                    ];
                }
            }
        }

        return [
            'success'      => true,
            'directory'    => $directory,
            'search_term'  => $search_term,
            'file_pattern' => $file_pattern,
            'matches'      => $matches,
            'count'        => count( $matches ),
        ];
    }

    /**
     * Get lines around a specific line for context
     *
     * @param array $lines       All lines.
     * @param int   $line_number Current line number (0-indexed).
     * @param int   $context     Number of lines before/after.
     * @return array Context lines.
     */
    private function get_line_context( array $lines, int $line_number, int $context ): array {
        $start = max( 0, $line_number - $context );
        $end   = min( count( $lines ) - 1, $line_number + $context );

        $result = [];
        for ( $i = $start; $i <= $end; $i++ ) {
            $result[ $i + 1 ] = $lines[ $i ];
        }

        return $result;
    }

    /**
     * Copy a file
     *
     * @param string $source      Source file path.
     * @param string $destination Destination file path.
     * @param bool   $overwrite   Overwrite if exists.
     * @return array Result with success status.
     */
    public function copy_file( string $source, string $destination, bool $overwrite = false ): array {
        $source_path = $this->normalize_path( $source );
        $dest_path   = $this->normalize_path( $destination );

        if ( ! $this->is_safe_path( $source_path ) || ! $this->is_safe_path( $dest_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied: Path is outside allowed directories', 'creator-core' ),
            ];
        }

        if ( ! $this->filesystem->exists( $source_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Source file not found', 'creator-core' ),
            ];
        }

        if ( $this->filesystem->exists( $dest_path ) && ! $overwrite ) {
            return [
                'success' => false,
                'error'   => __( 'Destination file already exists', 'creator-core' ),
            ];
        }

        $result = $this->filesystem->copy( $source_path, $dest_path, $overwrite );

        if ( ! $result ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to copy file', 'creator-core' ),
            ];
        }

        $this->logger->info( 'file_copied', [
            'source'      => $source_path,
            'destination' => $dest_path,
        ]);

        return [
            'success'     => true,
            'source'      => $source_path,
            'destination' => $dest_path,
            'message'     => __( 'File copied successfully', 'creator-core' ),
        ];
    }

    /**
     * Create a backup of a file
     *
     * @param string $file_path File to backup.
     * @return array Result with backup path.
     */
    public function create_backup( string $file_path ): array {
        $absolute_path = $this->normalize_path( $file_path );

        if ( ! $this->filesystem->exists( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'File not found', 'creator-core' ),
            ];
        }

        $backup_dir = WP_CONTENT_DIR . '/creator-backups/files/' . gmdate( 'Y-m-d' );
        if ( ! $this->filesystem->exists( $backup_dir ) ) {
            wp_mkdir_p( $backup_dir );
        }

        $filename    = basename( $absolute_path );
        $backup_name = gmdate( 'H-i-s' ) . '_' . $filename;
        $backup_path = $backup_dir . '/' . $backup_name;

        $result = $this->filesystem->copy( $absolute_path, $backup_path );

        if ( ! $result ) {
            return [
                'success' => false,
                'error'   => __( 'Failed to create backup', 'creator-core' ),
            ];
        }

        return [
            'success'     => true,
            'backup_path' => $backup_path,
            'original'    => $absolute_path,
        ];
    }

    /**
     * Normalize a file path
     *
     * @param string $path File path.
     * @return string Normalized absolute path.
     */
    private function normalize_path( string $path ): string {
        // If already absolute, use as is
        if ( strpos( $path, ABSPATH ) === 0 ) {
            return wp_normalize_path( $path );
        }

        // If starts with WP_CONTENT_DIR
        if ( strpos( $path, WP_CONTENT_DIR ) === 0 ) {
            return wp_normalize_path( $path );
        }

        // Make relative paths absolute from ABSPATH
        return wp_normalize_path( ABSPATH . ltrim( $path, '/' ) );
    }

    /**
     * Check if path is within allowed directories
     *
     * @param string $path Absolute path.
     * @return bool
     */
    private function is_safe_path( string $path ): bool {
        $allowed_bases = [
            wp_normalize_path( ABSPATH ),
            wp_normalize_path( WP_CONTENT_DIR ),
        ];

        $normalized = wp_normalize_path( realpath( dirname( $path ) ) ?: $path );

        foreach ( $allowed_bases as $base ) {
            if ( strpos( $normalized, $base ) === 0 ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if path is protected (WordPress core)
     *
     * @param string $path Absolute path.
     * @return bool
     */
    private function is_protected_path( string $path ): bool {
        $normalized = wp_normalize_path( $path );

        foreach ( $this->protected_paths as $protected ) {
            $protected_path = wp_normalize_path( ABSPATH . $protected );
            if ( strpos( $normalized, $protected_path ) === 0 ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get file info
     *
     * @param string $file_path File path.
     * @return array File information.
     */
    public function get_file_info( string $file_path ): array {
        $absolute_path = $this->normalize_path( $file_path );

        if ( ! $this->is_safe_path( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'Access denied', 'creator-core' ),
            ];
        }

        if ( ! $this->filesystem->exists( $absolute_path ) ) {
            return [
                'success' => false,
                'error'   => __( 'File not found', 'creator-core' ),
            ];
        }

        $is_dir = $this->filesystem->is_dir( $absolute_path );

        return [
            'success'     => true,
            'path'        => $absolute_path,
            'name'        => basename( $absolute_path ),
            'type'        => $is_dir ? 'directory' : 'file',
            'size'        => $is_dir ? 0 : $this->filesystem->size( $absolute_path ),
            'modified'    => $this->filesystem->mtime( $absolute_path ),
            'readable'    => $this->filesystem->is_readable( $absolute_path ),
            'writable'    => $this->filesystem->is_writable( $absolute_path ),
            'extension'   => $is_dir ? null : pathinfo( $absolute_path, PATHINFO_EXTENSION ),
        ];
    }

    /**
     * Get WordPress directory paths
     *
     * @return array Directory paths.
     */
    public function get_wordpress_paths(): array {
        return [
            'abspath'      => ABSPATH,
            'wp_content'   => WP_CONTENT_DIR,
            'plugins'      => WP_PLUGIN_DIR,
            'themes'       => get_theme_root(),
            'uploads'      => wp_upload_dir()['basedir'],
            'mu_plugins'   => WPMU_PLUGIN_DIR,
            'active_theme' => get_stylesheet_directory(),
        ];
    }
}
