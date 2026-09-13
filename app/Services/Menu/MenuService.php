<?php

namespace App\Services\Menu;

use App\Models\AdminMenu;
use App\Models\Menu;
use App\Models\PublicMenu;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the two database-driven navigation trees.
 *
 * The raw tree is cached because it changes only when an administrator edits a
 * menu; permission filtering then happens per request against the cached tree,
 * so a user's grants are never baked into shared cache.
 */
class MenuService
{
    public const ADMIN_CACHE_KEY = 'menus.admin.tree';

    public const PUBLIC_CACHE_KEY = 'menus.public.tree';

    /** @return Collection<int, AdminMenu> */
    public function adminTree(?User $user): Collection
    {
        $tree = Cache::rememberForever(
            self::ADMIN_CACHE_KEY,
            fn () => AdminMenu::query()->active()->roots()->with('childrenRecursive')->orderBy('sort_order')->get(),
        );

        return $this->filterByPermission($tree, $user);
    }

    /** @return Collection<int, PublicMenu> */
    public function publicTree(): Collection
    {
        return Cache::rememberForever(
            self::PUBLIC_CACHE_KEY,
            fn () => PublicMenu::query()->active()->roots()->with('childrenRecursive')->orderBy('sort_order')->get(),
        );
    }

    public function forget(): void
    {
        Cache::forget(self::ADMIN_CACHE_KEY);
        Cache::forget(self::PUBLIC_CACHE_KEY);
    }

    /**
     * Drops nodes the user may not reach. A parent whose own permission is not
     * granted survives when a child is visible, otherwise the child would be
     * unreachable from the sidebar; conversely a parent whose children are all
     * hidden is dropped rather than left as an empty heading.
     *
     * @param  Collection<int, AdminMenu>  $nodes
     * @return Collection<int, AdminMenu>
     */
    private function filterByPermission(Collection $nodes, ?User $user): Collection
    {
        // Built explicitly rather than via map()->filter(): dropping nodes with
        // a nullable map downgrades an Eloquent collection to a base one, and
        // the relations set below must stay Eloquent collections.
        $visible = new Collection();

        foreach ($nodes as $node) {
            $isGroup = $node->loadedChildren()->isNotEmpty();
            $children = $this->filterByPermission($node->loadedChildren(), $user);

            $node->setRelation('childrenRecursive', $children);
            $node->setRelation('children', $children);

            // A grouping node follows its children: it exists to reach them, so
            // once they are all hidden it would only be a dead heading. A leaf
            // follows its own permission, and a leaf without one is public.
            $visible_ = $isGroup ? $children->isNotEmpty() : $this->allows($node, $user);

            if ($visible_) {
                $visible->push($node);
            }
        }

        return $visible;
    }

    private function allows(Menu $node, ?User $user): bool
    {
        $permission = $node->permission ?? null;

        if (blank($permission)) {
            return true;
        }

        return $user?->can($permission) ?? false;
    }
}

