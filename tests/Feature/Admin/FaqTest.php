<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Faq;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Admin');
    }

    public function test_the_faq_screens_render(): void
    {
        $faq = Faq::create(['question' => 'Apa itu?', 'answer' => 'Penjelasan.']);

        foreach ([
            route('admin.faq.index'),
            route('admin.faq.create'),
            route('admin.faq.edit', $faq),
            route('admin.faq.category.index'),
        ] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_an_faq_can_be_created_under_a_category(): void
    {
        $category = Category::create(['type' => Category::TYPE_FAQ, 'name' => 'Layanan']);

        $this->actingAs($this->admin)->post(route('admin.faq.store'), [
            'question' => 'Berapa lama proses izin?',
            'answer' => 'Tujuh hari kerja sejak berkas lengkap.',
            'category_id' => $category->id,
            'sort_order' => 10,
            'is_active' => 1,
        ])->assertRedirect(route('admin.faq.index'));

        $faq = Faq::first();

        $this->assertSame('Berapa lama proses izin?', $faq->question);
        $this->assertSame($category->id, $faq->category_id);
        $this->assertTrue($faq->is_active);
    }

    public function test_an_faq_cannot_use_a_category_from_another_module(): void
    {
        $newsCategory = Category::create(['type' => Category::TYPE_NEWS, 'name' => 'Infrastruktur']);

        $this->actingAs($this->admin)->post(route('admin.faq.store'), [
            'question' => 'Pertanyaan',
            'answer' => 'Jawaban',
            'category_id' => $newsCategory->id,
        ])->assertSessionHasErrors('category_id');
    }

    public function test_the_datatable_returns_a_plain_text_excerpt(): void
    {
        Faq::create([
            'question' => 'Ada markup?',
            'answer' => '<p>Jawaban <strong>berformat</strong>.</p>',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('admin.faq.data').'?draw=1&start=0&length=10')
            ->assertOk();

        $excerpt = $response->json('data.0.answer_excerpt');

        $this->assertStringNotContainsString('<strong>', $excerpt);
        $this->assertStringContainsString('berformat', $excerpt);
    }

    public function test_an_faq_can_be_updated_and_deleted(): void
    {
        $faq = Faq::create(['question' => 'Lama', 'answer' => 'Jawaban']);

        $this->actingAs($this->admin)->put(route('admin.faq.update', $faq), [
            'question' => 'Baru',
            'answer' => 'Jawaban baru',
        ])->assertRedirect();

        $this->assertSame('Baru', $faq->fresh()->question);

        $this->actingAs($this->admin)->delete(route('admin.faq.destroy', $faq))->assertRedirect();
        $this->assertSame(0, Faq::count());
    }

    public function test_a_viewer_cannot_create_faqs(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('Viewer');

        $this->actingAs($viewer)->post(route('admin.faq.store'), [
            'question' => 'Tidak boleh',
            'answer' => 'Tidak boleh',
        ])->assertForbidden();
    }
}
