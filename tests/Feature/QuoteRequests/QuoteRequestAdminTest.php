<?php

declare(strict_types=1);

namespace Tests\Feature\QuoteRequests;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Lunar\Admin\Models\Staff;
use Pko\QuoteRequests\Enums\QuoteRequestMode;
use Pko\QuoteRequests\Enums\QuoteRequestStatus;
use Pko\QuoteRequests\Filament\Resources\QuoteRequestResource\Pages\ListQuoteRequests;
use Pko\QuoteRequests\Filament\Resources\QuoteRequestResource\Pages\ViewQuoteRequest;
use Pko\QuoteRequests\Models\QuoteRequest;
use Pko\QuoteRequests\Models\QuoteRequestFile;
use Tests\TestCase;

class QuoteRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    private QuoteRequest $request;

    private QuoteRequestFile $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');

        $this->request = QuoteRequest::create([
            'mode' => QuoteRequestMode::PartPhoto,
            'name' => 'Camille Martin',
            'email' => 'camille@example.test',
            'message' => 'Pièce à identifier.',
            'lines' => [['label' => 'MT-10', 'quantity' => 2]],
            'reference' => 'MT-10',
            'context' => ['product_name' => 'Moteur tubulaire'],
        ]);

        Storage::disk('local')->put('quote-requests/'.$this->request->id.'/abc.jpg', 'jpeg-bytes');
        $this->file = $this->request->files()->create([
            'disk' => 'local',
            'path' => 'quote-requests/'.$this->request->id.'/abc.jpg',
            'original_name' => 'plaque.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 10,
        ]);
    }

    private function actingAsAdmin(): Staff
    {
        $staff = Staff::create([
            'first_name' => 'Quote',
            'last_name' => 'Admin',
            'email' => 'quote-admin@example.test',
            'password' => bcrypt('password'),
            'admin' => true,
        ]);
        $this->actingAs($staff, 'staff');

        return $staff;
    }

    public function test_list_and_view_pages_render_for_admin(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/quote-requests')->assertOk()->assertSee('Camille Martin');
        $this->get('/admin/quote-requests/'.$this->request->id)
            ->assertOk()
            ->assertSee($this->request->number())
            ->assertSee('plaque.jpg')
            ->assertSee('Moteur tubulaire')
            ->assertSee(route('quote-requests.files.download', $this->file), false);
    }

    public function test_list_filters_by_mode_and_status(): void
    {
        $this->actingAsAdmin();
        $other = QuoteRequest::create([
            'mode' => QuoteRequestMode::Project,
            'status' => QuoteRequestStatus::Done,
            'name' => 'Autre Contact',
            'email' => 'autre@example.test',
            'message' => 'Projet.',
        ]);

        Livewire::test(ListQuoteRequests::class)
            ->filterTable('mode', 'part_photo')
            ->assertCanSeeTableRecords([$this->request])
            ->assertCanNotSeeTableRecords([$other])
            ->resetTableFilters()
            ->filterTable('status', 'done')
            ->assertCanSeeTableRecords([$other])
            ->assertCanNotSeeTableRecords([$this->request]);
    }

    public function test_status_can_be_changed(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ViewQuoteRequest::class, ['record' => $this->request->getRouteKey()])
            ->callAction('changeStatus', ['status' => 'in_progress', 'admin_notes' => 'Rappelé le client.'])
            ->assertHasNoActionErrors();

        $this->request->refresh();
        $this->assertSame(QuoteRequestStatus::InProgress, $this->request->status);
        $this->assertSame('Rappelé le client.', $this->request->admin_notes);
    }

    public function test_admin_downloads_private_attachment(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('quote-requests.files.download', $this->file));
        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('plaque.jpg', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_attachment_is_not_reachable_without_staff_session(): void
    {
        $this->get(route('quote-requests.files.download', $this->file))->assertRedirect();
        $this->get('/admin/quote-requests')->assertRedirect();
    }

    public function test_download_follows_the_model_policy(): void
    {
        $this->actingAsAdmin();
        Gate::policy(QuoteRequest::class, DenyQuoteRequestPolicy::class);

        $this->get(route('quote-requests.files.download', $this->file))->assertForbidden();
    }

    public function test_customer_session_cannot_download(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('quote-requests.files.download', $this->file))
            ->assertRedirect();
    }

    public function test_deleting_a_request_removes_its_files_from_disk(): void
    {
        $path = $this->file->path;
        $this->request->delete();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, QuoteRequestFile::query()->count());
    }
}

final class DenyQuoteRequestPolicy
{
    public function view(mixed $user, QuoteRequest $request): bool
    {
        return false;
    }
}
