<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAuthLog;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserAuthLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_user::auth::log');
    }

    public function view(User $user, UserAuthLog $userAuthLog): bool
    {
        return $user->can('view_user::auth::log');
    }

    public function create(User $user): bool
    {
        return $user->can('create_user::auth::log');
    }

    public function update(User $user, UserAuthLog $userAuthLog): bool
    {
        return $user->can('update_user::auth::log');
    }

    public function delete(User $user, UserAuthLog $userAuthLog): bool
    {
        return $user->can('delete_user::auth::log');
    }

    public function restore(User $user, UserAuthLog $userAuthLog): bool
    {
        return $user->can('restore_user::auth::log');
    }

    public function forceDelete(User $user, UserAuthLog $userAuthLog): bool
    {
        return $user->can('force_delete_user::auth::log');
    }
}
