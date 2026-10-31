<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminCreateUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_open_the_creation_form_from_user_management(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('users.index'))->assertOk()->assertSee(route('users.create'));
        $this->get(route('users.create'))->assertOk()->assertSee('Create a teacher or student account');
    }

    #[TestWith(['teacher'])]
    #[TestWith(['student'])]
    public function test_admin_can_create_an_active_account_that_can_log_in(string $role): void
    {
        $admin = User::factory()->admin()->create();
        $data = $this->accountData() + ['role' => $role, 'is_active' => false];

        $this->actingAs($admin)->post(route('users.store'), $data)->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => $role, 'is_active' => true, 'university_id' => 'UNI123']);
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->assertNotEmpty($user->portfolio_slug);
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('activities', ['user_id' => $admin->id, 'action' => 'User created', 'title' => 'New User']);

        $this->post(route('logout'));
        $this->post(route('login.store'), ['identity' => $data['email'], 'password' => $data['password'], 'role' => $role])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    #[TestWith(['teacher'])]
    #[TestWith(['student'])]
    public function test_non_admin_cannot_open_or_submit_account_creation(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get(route('users.create'))->assertForbidden();
        $this->post(route('users.store'), $this->accountData() + ['role' => 'student'])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'new@example.test']);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_guest_cannot_create_accounts(): void
    {
        $this->get(route('users.create'))->assertRedirect(route('login'));
        $this->post(route('users.store'), $this->accountData() + ['role' => 'student'])->assertRedirect(route('login'));

        $this->assertDatabaseCount('users', 0);
    }

    #[TestWith(['role', 'admin'])]
    #[TestWith(['password', 'short'])]
    #[TestWith(['password_confirmation', 'different'])]
    #[TestWith(['email', 'invalid'])]
    public function test_invalid_account_details_are_rejected(string $field, string $value): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $data = array_replace($this->accountData() + ['role' => 'student'], [$field => $value]);

        $this->post(route('users.store'), $data)->assertSessionHasErrors($field === 'password_confirmation' ? 'password' : $field);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('activities', 0);
    }

    public function test_email_and_university_id_must_be_unique(): void
    {
        $existing = User::factory()->create(['email' => 'new@example.test', 'university_id' => 'UNI123']);
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('users.store'), $this->accountData() + ['role' => 'teacher'])->assertSessionHasErrors(['email', 'university_id']);

        $this->assertDatabaseCount('users', 2);
        $this->assertSame('student', $existing->fresh()->role);
    }

    public function test_required_account_details_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('users.store'), [])->assertSessionHasErrors(['name', 'email', 'university_id', 'role', 'password']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_users_table_loads_cdn_datatables_with_all_authorized_rows(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->count(14)->create();

        $this->get(route('users.index'))->assertOk()->assertViewHas('users', fn ($users): bool => $users->count() === 15)->assertSee('https://cdn.datatables.net/v/dt/dt-3.1.5/datatables.min.js', false)->assertSee('id="users-table"', false)->assertDontSee('data-users-table-controls', false)->assertDontSee('class="search-bar"', false);
    }

    public function test_main_layout_includes_shared_table_and_toast_libraries_and_escapes_flash_messages(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->withSession(['status' => '<script>alert(1)</script>'])->get(route('dashboard'))->assertOk()->assertSee('https://cdn.datatables.net/v/dt/dt-3.1.5/datatables.min.js', false)->assertSee('https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.min.js', false)->assertSee('https://code.jquery.com/jquery-3.7.1.min.js', false)->assertSee('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js', false)->assertSee('https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css', false)->assertSee('data-toast-type="success"', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    /** @return array<string, string> */
    private function accountData(): array
    {
        return [
            'name' => 'New User',
            'email' => 'new@example.test',
            'university_id' => 'UNI123',
            'password' => 'StrongPassword1!',
            'password_confirmation' => 'StrongPassword1!',
        ];
    }
}
