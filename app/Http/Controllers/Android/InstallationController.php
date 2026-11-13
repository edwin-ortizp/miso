<?php

namespace App\Http\Controllers\Android;

use App\Http\Controllers\Controller;
use App\Models\AndroidInstallation;
use App\Services\Notifications\Android\FcmGateway;
use App\Services\Notifications\NotificationPreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstallationController extends Controller
{
    public function status(Request $request)
    {
        $data = $this->identity($request);
        $device = AndroidInstallation::find($data['installation_id']);
        if ($device) {
            abort_unless(hash_equals($device->secret_hash, hash('sha256', $data['secret'])), 403);
        }
        $owned = $device && $device->user_id === $request->user()->id && $device->active;

        return response()->json(['configured' => app(FcmGateway::class)->problem() === null,
            'binding' => $owned ? $device->binding : null, 'active' => (bool) $owned]);
    }

    public function store(Request $request)
    {
        abort_unless(app(FcmGateway::class)->problem() === null, 503, 'Canal Android sin configurar.');
        $data = $this->identity($request) + $request->validate(['token' => 'required|string|max:4096']);
        [$device, $activated] = DB::transaction(function () use ($request, $data) {
            $device = AndroidInstallation::whereKey($data['installation_id'])->lockForUpdate()->first();
            if ($device) {
                abort_unless(hash_equals($device->secret_hash, hash('sha256', $data['secret'])), 403);
            }
            // A token cannot be registered under another installation by guessing its value.
            abort_if(AndroidInstallation::where('token_hash', hash('sha256', $data['token']))->where('id', '!=', $data['installation_id'])->exists(), 409);
            $same = $device && $device->active && $device->user_id === $request->user()->id;
            $device ??= new AndroidInstallation(['id' => $data['installation_id']]);
            $device->fill(['user_id' => $request->user()->id, 'secret_hash' => hash('sha256', $data['secret']),
                'token' => $data['token'], 'token_hash' => hash('sha256', $data['token']), 'active' => true,
                'binding' => $same ? $device->binding : (string) Str::uuid(), 'activated_at' => $same ? $device->activated_at : now('UTC')])->save();

            return [$device, ! $same];
        });
        $request->session()->put('android_installation', $device->id);
        if ($activated) {
            $preferences = app(NotificationPreferences::class);
            $current = $preferences->get($request->user()->id);
            $preferences->save($request->user()->id, array_replace($current, ['guardian_nudges' => true]));
        }

        return response()->json(['binding' => $device->binding, 'active' => true]);
    }

    public function destroy(Request $request)
    {
        $data = $this->identity($request);
        $device = AndroidInstallation::where('user_id', $request->user()->id)->find($data['installation_id']);
        if ($device) {
            abort_unless(hash_equals($device->secret_hash, hash('sha256', $data['secret'])), 403);
            $device->update(['active' => false, 'binding' => (string) Str::uuid()]);
        }
        $request->session()->forget('android_installation');

        return response()->json(['active' => false]);
    }

    private function identity(Request $request): array
    {
        return $request->validate(['installation_id' => 'required|uuid', 'secret' => 'required|string|size:64|regex:/^[a-f0-9]+$/']);
    }
}
