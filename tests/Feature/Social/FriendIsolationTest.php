<?php

namespace Tests\Feature\Social;

use App\Models\Friendship;
use App\Models\Relationship;
use App\Models\User;
use App\Services\Social\Friends;
use Livewire\Livewire;

class FriendIsolationTest extends FriendTestCase
{
    public function test_third_party_cannot_act_on_someone_elses_friendship(): void
    {
        [$ana, $luis, $tercero] = User::factory()->count(3)->create();
        $friends = app(Friends::class);
        $friends->request($ana, $luis->friend_code);
        $id = Friendship::first()->id;

        $this->assertFalse($friends->accept($tercero, $id));
        $friends->decline($tercero, $id);
        $friends->block($tercero, $id);

        $this->assertSame(Friendship::PENDING, Friendship::first()->status);
        $this->assertCount(0, $friends->incoming($tercero));
        $this->assertCount(0, $friends->outgoing($tercero));
    }

    public function test_page_renders_code_and_never_other_users_private_data(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create(['name' => 'Persona']);
        Relationship::factory()->create(['user_id' => $luis->id, 'full_name' => 'Secreta Privada', 'general_notes' => 'nota privada']);

        $this->actingAs($ana)->get(route('social.friends'))
            ->assertOk()->assertSee('MISO-'.$ana->fresh()->friend_code)
            ->assertDontSee('Secreta Privada')->assertDontSee('nota privada');
    }

    public function test_username_validation_and_uniqueness(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();
        $luis->update(['username' => 'tomado']);

        Livewire::actingAs($ana)->test(\App\Livewire\Social\FriendsIndex::class)
            ->set('username', 'Tomado')->call('saveUsername')->assertHasErrors('username')
            ->set('username', 'ab')->call('saveUsername')->assertHasErrors('username')
            ->set('username', 'admin')->call('saveUsername')->assertHasErrors('username')
            ->set('username', '@Ana_01')->call('saveUsername')->assertHasNoErrors();

        $this->assertSame('ana_01', $ana->fresh()->username);
    }

    public function test_livewire_flow_send_and_accept(): void
    {
        [$ana, $luis] = User::factory()->count(2)->create();

        Livewire::actingAs($ana)->test(\App\Livewire\Social\FriendsIndex::class)
            ->set('lookup', $luis->friend_code)->call('sendRequest')->assertHasNoErrors();
        $id = Friendship::first()->id;
        Livewire::actingAs($luis)->test(\App\Livewire\Social\FriendsIndex::class)
            ->assertSee($ana->name)->call('accept', $id);

        $this->assertSame(Friendship::ACCEPTED, Friendship::first()->status);
    }

    public function test_relationship_link_only_to_accepted_friends_and_survives_account_deletion(): void
    {
        [$ana, $luis, $tercero] = User::factory()->count(3)->create();
        Friendship::create(['requester_id' => $ana->id, 'addressee_id' => $luis->id, 'status' => Friendship::ACCEPTED]);
        $ficha = Relationship::factory()->create(['user_id' => $ana->id]);
        $component = fn () => Livewire::actingAs($ana)->test(\App\Livewire\Relationship\RelationshipShow::class, ['relationship' => $ficha->id]);

        $component()->set('friendChoice', (string) $tercero->id)->call('saveFriendLink')->assertHasErrors('friendChoice');
        $component()->set('friendChoice', (string) $luis->id)->call('saveFriendLink')->assertHasNoErrors();
        $this->assertSame($luis->id, $ficha->fresh()->friend_user_id);

        $luis->delete();
        $this->assertNotNull(Relationship::withoutGlobalScopes()->find($ficha->id));
        $this->assertNull($ficha->fresh()->friend_user_id);
        $this->assertSame(0, Friendship::count(), 'cascada al borrar la cuenta');
    }
}
