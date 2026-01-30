<?php

namespace App\Http\Middleware;

use App\Models\Module;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => $this->getAuthData($request),
            'menu' => fn() => $this->getMenuTree($request),
            'flash' => $this->getFlashMessages($request),
            'errors' => $this->getValidationErrors($request),
        ];
    }

    /**
     * Get authentication data for the current user
     */
    protected function getAuthData(Request $request): array
    {
        $user = $request->user();

        if (!$user) {
            return [
                'user' => null,
                'permissions' => [],
                'roles' => [],
            ];
        }

        // Cache user data for performance
        $cacheKey = "user_auth_data_{$user->id}";

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $roles = $this->getUserRoles($user);
            $permissions = $this->getUserPermissions($user);

            return [
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'full_name' => $user->full_name,
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'is_admin' => in_array('administrator', $roles) || in_array('admin', $roles),
                    'is_adviser' => in_array('adviser', $roles) || in_array('user_trabajador', $roles),
                ],
                'permissions' => $permissions,
                'roles' => $roles,
            ];
        });
    }

    /**
     * Get menu tree with user permissions filter
     */
    protected function getMenuTree(Request $request): array
    {
        $user = $request->user();

        // Si no hay usuario autenticado, retornar menú vacío
        if (!$user) {
            return [];
        }

        // Cache menu for performance
        $cacheKey = "menu_tree_{$user->id}_" . md5(implode(',', $this->getUserRoles($user)));

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            try {
                // Fetch only active modules, ordered by section and ordering
                $modules = Module::query()
                    ->where('active', 'S')
                    ->orderBy('section')
                    ->orderBy('parent_id')
                    ->orderBy('ordering')
                    ->get(['id', 'parent_id', 'key', 'title', 'route_name', 'href', 'icon', 'section', 'ordering', 'permissions_required']);

                // Filter modules based on user permissions
                $userPermissions = $this->getUserPermissions($user);
                $userRoles = $this->getUserRoles($user);
                $isAdmin = in_array('administrator', $userRoles) || in_array('admin', $userRoles);

                $filteredModules = $modules->filter(function ($module) use ($userPermissions, $isAdmin) {
                    // Admins can see everything
                    if ($isAdmin) {
                        return true;
                    }

                    // Check if user has required permissions
                    if (empty($module->permissions_required)) {
                        return true; // No permissions required
                    }

                    $requiredPermissions = is_array($module->permissions_required)
                        ? $module->permissions_required
                        : json_decode($module->permissions_required, true) ?? [];

                    // User needs at least one of the required permissions
                    return !empty(array_intersect($userPermissions, $requiredPermissions));
                });

                // Build plain array nodes to avoid mutating Eloquent models
                $nodes = [];
                foreach ($filteredModules as $m) {
                    $nodes[$m->id] = [
                        'id' => $m->id,
                        'parent_id' => $m->parent_id,
                        'key' => $m->key,
                        'title' => $m->title,
                        'route_name' => $m->route_name,
                        'href' => $m->href,
                        'icon' => $m->icon,
                        'section' => $m->section,
                        'ordering' => (int) $m->ordering,
                        'children' => [],
                    ];
                }

                // Link children using references
                foreach ($nodes as $id => &$node) {
                    if ($node['parent_id'] && isset($nodes[$node['parent_id']])) {
                        $nodes[$node['parent_id']]['children'][] = &$node;
                    }
                }
                unset($node); // break reference

                // Group roots by section
                $sections = [];
                foreach ($nodes as $id => $node) {
                    if (!$node['parent_id']) {
                        $sec = $node['section'] ?: 'General';
                        $sections[$sec] = $sections[$sec] ?? [];
                        $sections[$sec][] = $node;
                    }
                }

                // Sort and strip internal fields
                $out = [];
                foreach ($sections as $name => $roots) {
                    usort($roots, fn($a, $b) => $a['ordering'] <=> $b['ordering']);
                    $clean = function ($n) use (&$clean) {
                        $children = [];
                        if (!empty($n['children'])) {
                            usort($n['children'], fn($a, $b) => $a['ordering'] <=> $b['ordering']);
                            foreach ($n['children'] as $c) {
                                $children[] = $clean($c);
                            }
                        }
                        return [
                            'id' => $n['id'],
                            'key' => $n['key'],
                            'title' => $n['title'],
                            'route_name' => $n['route_name'],
                            'href' => $n['href'],
                            'icon' => $n['icon'],
                            'ordering' => $n['ordering'],
                            'children' => $children,
                        ];
                    };
                    $out[] = [
                        'section' => $name,
                        'items' => array_map($clean, $roots),
                    ];
                }
                return $out;
            } catch (\Exception $e) {
                Log::error('Error building menu tree', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'trace' => $e->getTraceAsString()
                ]);

                // Return empty menu on error
                return [];
            }
        });
    }

    /**
     * Get flash messages
     */
    protected function getFlashMessages(Request $request): array
    {
        return [
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
            'warning' => $request->session()->get('warning'),
            'info' => $request->session()->get('info'),
        ];
    }

    /**
     * Get validation errors
     */
    protected function getValidationErrors(Request $request): ?object
    {
        return $request->session()->get('errors')
            ? $request->session()->get('errors')->getBag('default')
            : (object) [];
    }

    /**
     * Get user roles
     */
    protected function getUserRoles($user): array
    {
        if (isset($user->roles)) {
            return is_array($user->roles) ? $user->roles : json_decode($user->roles, true) ?? [];
        }
        return [];
    }

    /**
     * Get user permissions
     */
    protected function getUserPermissions($user): array
    {
        if (isset($user->permissions)) {
            return is_array($user->permissions) ? $user->permissions : json_decode($user->permissions, true) ?? [];
        }
        return [];
    }
}
