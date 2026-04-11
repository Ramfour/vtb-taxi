<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\Request as FinalRequest;
use App\Models\TempRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_available(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_employee_can_create_request_in_buffer(): void
    {
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
        ]);

        $response = $this->post('/employee/requests?user_id='.$employee->id, [
            'user_id' => $employee->id,
            'full_name' => $employee->full_name,
            'phone' => $employee->phone,
            'address_raw' => 'г. Новосибирск, ул. Гоголя, 10',
            'date_time' => now()->addDay()->setTime(9, 15)->format('Y-m-d H:i:s'),
        ]);

        $response
            ->assertRedirect('/employee/requests?user_id='.$employee->id)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('temp_requests', [
            'user_id' => $employee->id,
            'address_raw' => 'г. Новосибирск, ул. Гоголя, 10',
            'status' => RequestStatus::Pending->value,
        ]);
    }

    public function test_manager_can_approve_and_finalize_request(): void
    {
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
        ]);

        $manager = User::factory()->manager()->create();

        $tempRequest = TempRequest::query()->create([
            'user_id' => $employee->id,
            'full_name' => $employee->full_name,
            'phone' => $employee->phone,
            'address_raw' => 'г. Новосибирск, ул. Орджоникидзе, 18',
            'date_time' => now()->addDay()->setTime(11, 0),
            'status' => RequestStatus::Pending,
        ]);

        $reviewResponse = $this->patch('/manager/requests/'.$tempRequest->id.'/review?user_id='.$manager->id, [
            'user_id' => $manager->id,
            'action' => 'approve',
            'manager_comment' => 'Согласовано на утренний слот.',
        ]);

        $reviewResponse
            ->assertRedirect('/manager/requests?user_id='.$manager->id)
            ->assertSessionHas('status');

        $this->assertDatabaseHas('temp_requests', [
            'id' => $tempRequest->id,
            'status' => RequestStatus::Approved->value,
            'reviewed_by' => $manager->id,
        ]);

        $finalizeResponse = $this->post('/manager/requests/finalize?user_id='.$manager->id, [
            'user_id' => $manager->id,
        ]);

        $finalizeResponse
            ->assertRedirect('/manager/requests?user_id='.$manager->id)
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('temp_requests', [
            'id' => $tempRequest->id,
        ]);

        $this->assertDatabaseHas('requests', [
            'user_id' => $employee->id,
            'status' => RequestStatus::Approved->value,
            'approved_by' => $manager->id,
            'temp_request_id' => $tempRequest->id,
        ]);

        $this->assertSame(1, FinalRequest::query()->count());
    }

    public function test_employee_cannot_open_manager_dashboard(): void
    {
        $employee = User::factory()->create([
            'role' => UserRole::Employee,
        ]);

        $response = $this->get('/manager/requests?user_id='.$employee->id);

        $response->assertForbidden();
    }
}
