<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\AssertableJson;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserTest extends TestCase
{
    use RefreshDatabase; // Az adatbázis hibák (no such table: users) elkerülésére
    
    protected function actingAsUser($user)
    {
        $token = JWTAuth::fromUser($user);
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    // ----------------------------------------------------------------------------------
    // 1. /users/me (GET) - Lekérés
    // ----------------------------------------------------------------------------------

    public function test_me_endpoint_requires_authentication()
    {
        // A hiba alapján a Laravel alapértelmezett üzenetét várjuk
        $response = $this->getJson('/api/users/me');
        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_me_endpoint_returns_user_data()
    {
        // ARRANGE: Felhasználó létrehozása
        $user = User::factory()->create(['role' => 'student']);
        
        // ACT: Kérés küldése JWT tokennel
        $response = $this->actingAsUser($user)->getJson('/api/users/me');

        // ASSERT: Ellenőrizzük a státuszt és a válasz struktúráját (userController.php alapján)
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'user' => ['id', 'name', 'email', 'role'],
                     'stats' => ['enrolledCourses', 'completedCourses']
                 ])
                 // Ellenőrizzük, hogy a válasz a helyes felhasználót tartalmazza
                 ->assertJsonPath('user.email', $user->email);
    }
    
    // ----------------------------------------------------------------------------------
    // 2. /users/me (PUT) - Profil Frissítés
    // ----------------------------------------------------------------------------------

    public function test_user_can_update_their_own_name_and_email()
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

        $newEmail = 'new@example.com';
        $newName = 'New Name';

        $response = $this->actingAsUser($user)->putJson('/api/users/me', [
            'name' => $newName,
            'email' => $newEmail,
        ]);

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Profile updated successfully'])
                 ->assertJsonPath('user.name', $newName)
                 ->assertJsonPath('user.email', $newEmail);

        // Ellenőrizzük az adatbázist
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $newName,
            'email' => $newEmail,
        ]);
    }
    
    public function test_user_can_update_their_password()
    {
        $user = User::factory()->create();

        $newPassword = 'New_Secure_Password_2025';

        $response = $this->actingAsUser($user)->putJson('/api/users/me', [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $response->assertStatus(200);

        // Frissítsük a felhasználót az adatbázisból és ellenőrizzük a jelszót
        $updatedUser = User::find($user->id);
        $this->assertTrue(Hash::check($newPassword, $updatedUser->password));
    }
    
    // ----------------------------------------------------------------------------------
    // 3. /users (GET) - Összes felhasználó listázása (Admin Only)
    // ----------------------------------------------------------------------------------

    public function test_student_cannot_access_user_list()
    {
        $student = User::factory()->create(['role' => 'student']);

        $response = $this->actingAsUser($student)->getJson('/api/users');

        // userController.php 120. sor: return response()->json(['message' => 'Forbidden'], 403);
        $response->assertStatus(403)
                 ->assertJson(['message' => 'Forbidden']);
    }

    public function test_admin_can_access_user_list()
    {
        // ARRANGE: Létrehozunk egy admin és néhány student felhasználót
        $admin = User::factory()->create(['role' => 'admin']);
        $students = User::factory(3)->create(['role' => 'student']);
        
        $response = $this->actingAsUser($admin)->getJson('/api/users');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data' => [
                     '*' => [
                         'user' => ['id', 'name', 'email', 'role'],
                         'stats' => ['enrolledCourses', 'completedCourses']
                     ]
                 ]])
                 // Ellenőrizzük, hogy az összes felhasználót (admin + 3 student) visszaadta
                 ->assertJson(fn (AssertableJson $json) =>
                     $json->has('data', 4)
                          ->etc()
                 );
    }
    
    // ----------------------------------------------------------------------------------
    // 4. /users/{id} (GET) - Felhasználó Megtekintése (Admin Only)
    // ----------------------------------------------------------------------------------

    public function test_admin_can_view_specific_user()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $targetUser = User::factory()->create(['role' => 'student', 'name' => 'Target User']);
        
        $response = $this->actingAsUser($admin)->getJson("/api/users/{$targetUser->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('user.name', 'Target User');
    }

    public function test_student_cannot_view_other_users()
    {
        $student = User::factory()->create(['role' => 'student']);
        $otherUser = User::factory()->create(['role' => 'student']);
        
        $response = $this->actingAsUser($student)->getJson("/api/users/{$otherUser->id}");

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Forbidden']);
    }
    
    // ----------------------------------------------------------------------------------
    // 5. /users/{id} (DELETE) - Felhasználó Törlése (Admin Only - Soft Delete)
    // ----------------------------------------------------------------------------------

    public function test_admin_can_soft_delete_a_user()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $userToDelete = User::factory()->create();
        
        $response = $this->actingAsUser($admin)->deleteJson("/api/users/{$userToDelete->id}");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'User deleted successfully']);

        // Ellenőrizzük, hogy a felhasználó soft deleted
        $this->assertSoftDeleted('users', ['id' => $userToDelete->id]);
    }

    public function test_student_cannot_delete_users()
    {
        $student = User::factory()->create(['role' => 'student']);
        $userToDelete = User::factory()->create();
        
        $response = $this->actingAsUser($student)->deleteJson("/api/users/{$userToDelete->id}");

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Forbidden']);

        // Ellenőrizzük, hogy a felhasználó NEM lett törölve
        $this->assertDatabaseHas('users', ['id' => $userToDelete->id]);
    }
}