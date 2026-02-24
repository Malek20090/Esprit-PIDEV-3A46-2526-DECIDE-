<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Email confirmation is disabled: accounts can sign in immediately.
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
