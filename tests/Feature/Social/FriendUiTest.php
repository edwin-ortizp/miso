<?php

namespace Tests\Feature\Social;

use App\Livewire\Relationship\RelationshipShow;
use App\Livewire\Social\FriendsIndex;
use App\Models\Relationship;
use App\Models\User;
use Livewire\Livewire;

class FriendUiTest extends FriendTestCase
{
    public function test_linked_friend_is_shown_on_the_relationship_card(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create(['name' => 'Persona']);
        $luis->update(['username' => 'luis_ui', 'name' => 'Luis Gómez']);
        $this->befriend($ana, $luis);
        $ficha = Relationship::factory()->create(['user_id' => $ana->id, 'friend_user_id' => $luis->id]);

        Livewire::actingAs($ana)->test(RelationshipShow::class, ['relationship' => $ficha->id])
            ->assertSee('Amigo en MiSo')->assertSee('Luis Gómez')->assertSee('luis_ui');
    }

    public function test_friends_show_avatar_initials_and_only_https_photos(): void
    {
        [$ana, $luis, $sofi] = User::factory()->count(3)->create();
        $luis->update(['name' => 'Luis Gómez', 'avatar_url' => 'https://example.test/luis.png']);
        $sofi->update(['name' => 'Sofi Ruiz', 'avatar_url' => 'http://tracker.test/pixel.gif']);
        $this->befriend($ana, $luis);
        $this->befriend($ana, $sofi);

        $this->actingAs($ana)->get(route('social.friends'))
            ->assertOk()->assertSee('https://example.test/luis.png', false)
            ->assertDontSee('tracker.test', false)->assertSee('SR');
    }

    public function test_username_is_offered_only_the_first_time(): void
    {
        $ana = User::factory()->create();

        Livewire::actingAs($ana)->test(FriendsIndex::class)->assertSet('showUsername', true);
        Livewire::actingAs($ana)->test(FriendsIndex::class)->assertSet('showUsername', false);

        $luis = User::factory()->create(['username' => 'ya_tengo']);
        Livewire::actingAs($luis)->test(FriendsIndex::class)->assertSet('showUsername', false);
    }
}
