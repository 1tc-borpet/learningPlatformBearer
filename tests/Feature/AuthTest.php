<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;
    public function test_ping_endpoint_returns_ok()
    {
        $response = $this->getJson('/api/ping');
        $response->assertStatus(200)
                ->assertJson(['message' => 'API works!']);
    }

    public function test_register_creates_user()
    {
        $payload = [
            'name' => 'Teszt Elek',
            'email' => 'teszt@example.com',
            'password' => 'Jelszo_2025',
            'password_confirmation' => 'Jelszo_2025'
        ];

        $response = $this->postJson('/api/register', $payload);
        $response->assertStatus(201)
                ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email', 'role']]);
        
        // Ellenőrizzük, hogy a felhasználó létrejött az adatbázisban
        $this->assertDatabaseHas('users', [
            'email' => 'teszt@example.com',
        ]);
    }

    public function test_login_with_valid_credentials()
    {
        // ARRANGE: Felhasználó létrehozása az adatbázisban
        // Mivel a regisztrációs teszt csak egyszer fut, létre kell hozni egy felhasználót 
        // minden login teszthez.
        $password = 'Jelszo_2025';
        $user = User::factory()->create([
            'email' => 'validuser@example.com',
            'password' => Hash::make($password), // A jelszót hash-elni kell!
        ]);

        // ACT: Bejelentkezési kérés
        $response = $this->postJson('/api/login', [
            'email' => 'validuser@example.com',
            'password' => $password, // A bejelentkezéshez a plain text jelszót adjuk
        ]);

        // ASSERT: Ellenőrizzük a státuszt és a válasz struktúráját (JWT)
        $response->assertStatus(200)
                 ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email', 'role'], 'access' => ['token', 'token_type', 'expires_in']]);

        // JWT nem tárol tokeneket az adatbázisban, ezért ezt nem ellenőrizzük
        // A token a válaszban van és JWT formátumú
    }

    public function test_login_with_invalid_credentials()
    {
        // ARRANGE: Létrehozzuk a létező felhasználót
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('CorrectPassword'), 
        ]);

        // ACT: Helytelen adatokkal próbálkozunk
        $response = $this->postJson('/api/login', [
            'email' => 'existing@example.com',
            'password' => 'wrongpass' // Helytelen jelszó
        ]);

        // ASSERT: Ellenőrizzük az elutasítást
        // FIGYELEM: Ha a backend 422-t ad vissza validációs hiba (pl. hiányzó mező) helyett, 
        // de az invalid credentials hiba 401, akkor az a helyes.
        $response->assertStatus(401)
                 ->assertJson(['message' => 'Invalid email or password']);
    }

}