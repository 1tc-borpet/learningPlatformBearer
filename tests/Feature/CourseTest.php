<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tymon\JWTAuth\Facades\JWTAuth;

class CourseTest extends TestCase
{
    use RefreshDatabase; // Elengedhetetlen az adatbázis táblák létrehozásához
    
    protected function actingAsUser($user)
    {
        $token = JWTAuth::fromUser($user);
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    // ----------------------------------------------------------------------------------
    // 1. /courses (GET) - Lista lekérése
    // ----------------------------------------------------------------------------------

    public function test_course_index_requires_authentication()
    {
        $response = $this->getJson('/api/courses');

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_course_index_returns_list_of_courses()
    {
        // ARRANGE: Felhasználó és 3 kurzus manuális létrehozása
        $user = User::factory()->create();
        
        // <<< MANUÁLIS LÉTREHOZÁS FACTORY HELYETT
        Course::create(['title' => 'Kurzus A', 'description' => 'Leírás A']);
        Course::create(['title' => 'Kurzus B', 'description' => 'Leírás B']);
        Course::create(['title' => 'Kurzus C', 'description' => 'Leírás C']);
        
        // ACT: Kérés küldése JWT tokennel
        $response = $this->actingAsUser($user)->getJson('/api/courses');

        // ASSERT: Ellenőrizzük a státuszt és a struktúrát
        $response->assertStatus(200)
                 ->assertJsonStructure(['courses' => [
                     '*' => ['title', 'description']
                 ]])
                 ->assertJson(fn (AssertableJson $json) =>
                     $json->has('courses', 3) // Ellenőrizzük, hogy mindhárom kurzus visszajött
                          ->etc()
                 );
    }

    // ----------------------------------------------------------------------------------
    // 2. /courses/{id} (GET) - Kurzus részletek
    // ----------------------------------------------------------------------------------

    public function test_course_show_returns_details_and_students()
    {
        // ARRANGE: Admin és kurzus manuális létrehozása
        $user = User::factory()->create(['role' => 'admin']);
        // <<< MANUÁLIS LÉTREHOZÁS FACTORY HELYETT
        $course = Course::create(['title' => 'Részletes Kurzus', 'description' => 'Részletes Leírás']);
        $student1 = User::factory()->create();
        $student2 = User::factory()->create();

        // Beiratkozás: 1 beiratkozott, 1 befejezett
        $student1->courses()->attach($course->id, ['enrolled_at' => now()]);
        $student2->courses()->attach($course->id, ['enrolled_at' => now(), 'completed_at' => now()]);
        
        // ACT: Kérés küldése JWT tokennel
        $response = $this->actingAsUser($user)->getJson("/api/courses/{$course->id}");

        // ASSERT: Ellenőrizzük a státuszt és a fészkelt struktúrát
        $response->assertStatus(200)
                 ->assertJsonPath('course.title', $course->title)
                 ->assertJson(fn (AssertableJson $json) =>
                     $json->has('students', 2) // Két diák van
                          ->where('students.0.completed', false) // student1: nincs completed_at
                          ->where('students.1.completed', true) // student2: van completed_at
                          ->etc()
                 );
    }
    
    // ----------------------------------------------------------------------------------
    // 3. /courses/{id}/enroll (POST) - Beiratkozás
    // ----------------------------------------------------------------------------------

    public function test_user_can_enroll_in_a_course()
    {
        $user = User::factory()->create();
        // <<< MANUÁLIS LÉTREHOZÁS FACTORY HELYETT
        $course = Course::create(['title' => 'Beiratkozó Kurzus', 'description' => 'Leírás']);

        // ACT: Beiratkozás JWT tokennel
        $response = $this->actingAsUser($user)->postJson("/api/courses/{$course->id}/enroll");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Successfully enrolled in course']);

        // ASSERT: Ellenőrizzük a kapcsolótáblát
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'completed_at' => null,
        ]);
    }

    public function test_enrollment_fails_if_already_enrolled()
    {
        $user = User::factory()->create();
        // <<< MANUÁLIS LÉTREHOZÁS FACTORY HELYETT
        $course = Course::create(['title' => 'Már Beiratkozott Kurzus', 'description' => 'Leírás']);
        
        // Először beiratkozunk
        $user->courses()->attach($course->id, ['enrolled_at' => now()]);

        // ACT: Újra megpróbáljuk JWT tokennel
        $response = $this->actingAsUser($user)->postJson("/api/courses/{$course->id}/enroll");

        $response->assertStatus(409)
                 ->assertJson(['message' => 'Already enrolled in this course']);
    }

    // ----------------------------------------------------------------------------------
    // 4. /courses/{id}/completed (PATCH) - Teljesítés
    // ----------------------------------------------------------------------------------

    public function test_user_can_complete_an_enrolled_course()
    {
        $user = User::factory()->create();
        // <<< MANUÁLIS LÉTREHOZÁS FACTORY HELYETT
        $course = Course::create(['title' => 'Teljesíthő Kurzus', 'description' => 'Leírás']);
        
        // Beiratkozás
        $user->courses()->attach($course->id, ['enrolled_at' => now()]);

        // ACT: Teljesítés JWT tokennel
        $response = $this->actingAsUser($user)->patchJson("/api/courses/{$course->id}/completed");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Course completed']);

        // ASSERT: Ellenőrizzük, hogy a completed_at be lett állítva (nem null)
        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'completed_at' => null,
        ]);
    }

    public function test_complete_fails_if_not_enrolled()
    {
        $user = User::factory()->create();
        // <<< MANUÁLIS LÉTREHOZÁS FACTORY HELYETT
        $course = Course::create(['title' => 'Nem Beiratkozott Kurzus', 'description' => 'Leírás']);
        
        // ACT: Teljesítés beiratkozás nélkül JWT tokennel
        $response = $this->actingAsUser($user)->patchJson("/api/courses/{$course->id}/completed");

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Not enrolled in this course']);
    }
    
    public function test_complete_fails_if_already_completed()
    {
        $user = User::factory()->create();
        // <<< MANUÁLIS LÉTREHOZÁS FACTORY HELYETT
        $course = Course::create(['title' => 'Már Teljesített Kurzus', 'description' => 'Leírás']);
        
        // Beiratkozás és teljesítés
        $user->courses()->attach($course->id, ['enrolled_at' => now(), 'completed_at' => now()]);

        // ACT: Újra megpróbáljuk a teljesítést JWT tokennel
        $response = $this->actingAsUser($user)->patchJson("/api/courses/{$course->id}/completed");

        $response->assertStatus(409)
                 ->assertJson(['message' => 'Course already completed']);
    }
}