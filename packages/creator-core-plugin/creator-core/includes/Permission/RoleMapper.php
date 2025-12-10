<?php
/**
 * Role Mapper Stub
 *
 * Placeholder class for role mapping functionality.
 * Will be reimplemented in Phase 2.
 *
 * @package CreatorCore
 */

namespace CreatorCore\Permission;

defined( 'ABSPATH' ) || exit;

/**
 * Class RoleMapper
 *
 * Stub class for role mapping.
 */
class RoleMapper {

	/**
	 * Get role permissions (returns defaults)
	 *
	 * @param string $role Role name.
	 * @return array Default permissions.
	 */
	public function get_role_permissions( string $role ): array {
		return [
			'can_use_creator'     => $role === 'administrator',
			'can_execute_code'    => $role === 'administrator',
			'can_manage_files'    => $role === 'administrator',
			'can_manage_database' => $role === 'administrator',
		];
	}
}
