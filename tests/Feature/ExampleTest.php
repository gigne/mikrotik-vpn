<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL redirects to the dashboard for guests.
     */
    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')
            ->assertStatus(302)
            ->assertRedirect(route('dashboard'));
    }
}