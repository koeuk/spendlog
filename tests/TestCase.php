<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed the permission catalogue for every test that refreshes the database.
     *
     * The policies read permissions, and hasPermissionTo() throws
     * PermissionDoesNotExist — not false — when the catalogue is missing, so an
     * unseeded test fails for a reason that has nothing to do with what it is
     * testing. UserFactory also needs the rows to apply a role.
     */
    protected $seed = true;

    protected $seeder = RoleSeeder::class;
}
