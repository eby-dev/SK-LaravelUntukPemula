<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_stored_content_is_sanitized_through_the_model(): void
    {
        $post = Post::create([
            'image' => 'a.jpg',
            'title' => 'Judul Post Uji',
            'content' => '<p>halo</p><script>alert(document.cookie)</script>',
        ]);

        $this->assertStringNotContainsString('<script', $post->fresh()->content);
        $this->assertStringNotContainsString('alert', $post->fresh()->content);
        $this->assertStringContainsString('halo', $post->fresh()->content);
    }

    public function test_event_handlers_are_stripped_on_update(): void
    {
        $post = Post::create([
            'image' => 'a.jpg',
            'title' => 'Judul Post Uji',
            'content' => '<p>ok</p>',
        ]);

        $post->update(['content' => '<img src=x onerror=alert(1)>']);

        $this->assertStringNotContainsString('onerror', $post->fresh()->content);
    }

    public function test_guests_cannot_create_posts(): void
    {
        $this->post('/posts', [
            'title' => 'Judul Post Uji',
            'content' => 'Konten yang cukup panjang.',
        ]);

        // No login route is registered yet, so the auth middleware aborts
        // rather than redirecting. Either way the write must not land.
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_guests_cannot_delete_posts(): void
    {
        $post = Post::create([
            'image' => 'a.jpg',
            'title' => 'Judul Post Uji',
            'content' => 'Konten yang cukup panjang.',
        ]);

        $this->delete('/posts/'.$post->id);

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    }

    public function test_index_is_publicly_readable(): void
    {
        $this->get('/posts')->assertOk();
    }

    public function test_missing_post_returns_404(): void
    {
        $this->get('/posts/99999')->assertNotFound();
    }

    public function test_security_headers_are_present(): void
    {
        $this->get('/posts')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
