<?php

namespace Tests\Feature;

use App\Models\Core\UserRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserRoleTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'coach', 'client']);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->text('bio')->nullable();
            $table->integer('years_experience')->nullable();
            $table->json('specialties')->nullable();
            $table->json('similar_experiences')->nullable();
            $table->string('notification_email')->nullable();
            $table->string('timezone')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('available_now')->default(false);
            $table->timestamps();
        });
    }

    public function test_admin_can_change_a_users_role(): void
    {
        /** @var \App\Models\User $admin */
        $admin = User::factory()->create();
        UserRole::create([
            'user_id' => $admin->id,
            'role' => 'admin',
        ]);

        $targetUser = User::factory()->create();
        UserRole::create([
            'user_id' => $targetUser->id,
            'role' => 'client',
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/v1/admin/users/' . $targetUser->id . '/role', [
            'role' => 'coach',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.role', 'coach')
            ->assertJsonPath('data.user.id', $targetUser->id);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $targetUser->id,
            'role' => 'coach',
        ]);

        $this->assertDatabaseMissing('user_roles', [
            'user_id' => $targetUser->id,
            'role' => 'client',
        ]);
    }
}
