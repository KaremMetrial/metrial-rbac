<?php

namespace Metrial\RBAC\Tests\Integration;

use Illuminate\Support\Facades\Blade;
use Metrial\RBAC\Tests\TestCase;
use Metrial\RBAC\Tests\Concerns\CreatesRbacData;

class BladeDirectivesTest extends TestCase
{
    use CreatesRbacData;

    protected function setUp(): void
    {
        parent::setUp();
        $user = $this->createUser();
        $this->actingAs($user);
    }

    public function test_role_directive_compiles(): void
    {
        $result = Blade::compileString('@role("admin")content@endrole');
        $this->assertStringContainsString('Blade::check', $result);
    }

    public function test_hasanyrole_directive_compiles(): void
    {
        $result = Blade::compileString('@hasanyrole(["admin", "editor"])content@endhasanyrole');
        $this->assertStringContainsString('Blade::check', $result);
    }

    public function test_hasallroles_directive_compiles(): void
    {
        $result = Blade::compileString('@hasallroles(["admin", "editor"])content@endhasallroles');
        $this->assertStringContainsString('Blade::check', $result);
    }

    public function test_haspermission_directive_compiles(): void
    {
        $result = Blade::compileString('@haspermission("edit-posts")content@endhaspermission');
        $this->assertStringContainsString('Blade::check', $result);
    }

    public function test_role_directive_evaluates_correctly(): void
    {
        $user = auth()->user();
        $role = $this->createRole(['name' => 'Test Admin', 'slug' => 'test-admin-blade']);
        $this->assignRoleToUser($user, $role);

        // Use Blade::check which calls the directive's underlying logic
        $this->assertTrue(Blade::check('role', 'test-admin-blade'));
        $this->assertFalse(Blade::check('role', 'nonexistent-role'));
    }
}
