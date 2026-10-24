<?php

namespace App\Livewire\Settings;

use App\Mcp\Support\ToolCatalog;
use App\Models\IntegrationToken;
use Composer\InstalledVersions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Token;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Ajustes')]
class SettingsPage extends Component
{
    public string $fullName = '';

    public string $email = '';

    public ?float $currentWeightKg = null;

    public ?int $heightCm = null;

    public ?string $birthDate = null;

    public ?int $lifeExpectancyYears = null;

    public ?string $activityLevel = null;

    public ?int $dailyWaterGoal = null;

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public string $successMessage = '';

    public ?string $obsidianIntegrationToken = null;

    public ?string $calDavPassword = null;

    public ?string $aiIntegrationToken = null;

    public const SECTIONS = ['profile', 'body', 'security', 'oauth', 'integrations', 'notifications', 'mcp', 'logs', 'about'];

    public string $section = 'profile';

    #[Url(as: 'q', except: '')]
    public string $toolSearch = '';

    #[Url(as: 'tipo', except: '')]
    public string $toolKind = '';

    #[Url(as: 'modulo', except: '')]
    public string $toolModule = '';

    public ?string $selectedTool = null;

    public bool $toolOpen = false;

    public function mount(string $section = 'profile')
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        $this->section = $section;

        $user = Auth::user();
        $this->fullName = $user->full_name ?? $user->name ?? '';
        $this->email = $user->email;
        $this->currentWeightKg = $user->current_weight_kg !== null ? (float) $user->current_weight_kg : null;
        $this->heightCm = $user->height_cm;
        $this->birthDate = $user->birth_date?->toDateString();
        $this->lifeExpectancyYears = $user->life_expectancy_years;
        $this->activityLevel = $user->activity_level;
        $this->dailyWaterGoal = $user->daily_water_goal;
    }

    public function updateProfile()
    {
        $validated = $this->validate([
            'fullName' => ['required', 'string', 'max:255'],
            'currentWeightKg' => ['nullable', 'numeric', 'between:20,500'],
            'heightCm' => ['nullable', 'integer', 'between:50,300'],
            'birthDate' => ['nullable', 'date', 'before_or_equal:today'],
            'lifeExpectancyYears' => ['nullable', 'integer', 'between:1,130'],
            'activityLevel' => ['nullable', 'in:sedentary,light,moderate,high,very_high'],
            'dailyWaterGoal' => ['nullable', 'integer', 'between:500,10000'],
        ]);

        $user = Auth::user();
        $user->update([
            'full_name' => $validated['fullName'],
            'name' => $validated['fullName'],
            'current_weight_kg' => $validated['currentWeightKg'],
            'height_cm' => $validated['heightCm'],
            'birth_date' => $validated['birthDate'],
            'life_expectancy_years' => $validated['lifeExpectancyYears'],
            'activity_level' => $validated['activityLevel'],
            'daily_water_goal' => $validated['dailyWaterGoal'],
        ]);
        $this->successMessage = 'Perfil actualizado correctamente.';
    }

    public function updatePassword()
    {
        if (! Hash::check($this->currentPassword, Auth::user()->password)) {
            $this->addError('currentPassword', 'La contraseña actual no es correcta.');

            return;
        }

        if (strlen($this->newPassword) < 8) {
            $this->addError('newPassword', 'La nueva contraseña debe tener al menos 8 caracteres.');

            return;
        }

        if ($this->newPassword !== $this->newPasswordConfirmation) {
            $this->addError('newPasswordConfirmation', 'Las contraseñas no coinciden.');

            return;
        }

        Auth::user()->update([
            'password' => Hash::make($this->newPassword),
        ]);

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';
        $this->successMessage = 'Contraseña actualizada correctamente.';
    }

    public function createOrRotateObsidianToken(): void
    {
        [$integrationToken, $plainTextToken] = IntegrationToken::issueFor(Auth::user(), 'Obsidian / n8n', 'obsidian');

        $this->obsidianIntegrationToken = $plainTextToken;
        $this->successMessage = 'Token de integración generado. Cópialo ahora: no volverá a mostrarse.';
    }

    public function revokeObsidianToken(): void
    {
        IntegrationToken::query()
            ->where('user_id', Auth::id())
            ->where('purpose', 'obsidian')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $this->obsidianIntegrationToken = null;
        $this->successMessage = 'Token de integración revocado.';
    }

    public function hideObsidianToken(): void
    {
        $this->obsidianIntegrationToken = null;
    }

    public function createOrRotateCalDavPassword(): void
    {
        [, $plainTextToken] = IntegrationToken::issueFor(
            Auth::user(),
            'CalDAV',
            'caldav',
            IntegrationToken::CALDAV_PREFIX,
        );

        $this->calDavPassword = $plainTextToken;
        $this->successMessage = 'Contraseña CalDAV generada. Cópiala ahora: no volverá a mostrarse.';
    }

    public function revokeCalDavPassword(): void
    {
        IntegrationToken::query()
            ->where('user_id', Auth::id())
            ->where('purpose', 'caldav')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $this->calDavPassword = null;
        $this->successMessage = 'Acceso CalDAV revocado.';
    }

    public function hideCalDavPassword(): void
    {
        $this->calDavPassword = null;
    }

    public function createOrRotateAiToken(): void
    {
        [, $plainTextToken] = IntegrationToken::issueFor(
            Auth::user(),
            'IA / MCP',
            'ai',
            IntegrationToken::AI_PREFIX,
        );

        $this->aiIntegrationToken = $plainTextToken;
        $this->successMessage = 'Token de IA generado. Cópialo ahora: no volverá a mostrarse.';
    }

    public function revokeAiToken(): void
    {
        IntegrationToken::query()
            ->where('user_id', Auth::id())
            ->where('purpose', 'ai')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $this->aiIntegrationToken = null;
        $this->successMessage = 'Token de IA revocado.';
    }

    public function hideAiToken(): void
    {
        $this->aiIntegrationToken = null;
    }

    /** Corta el acceso de una app conectada por OAuth: su token y los refresh que cuelgan de él. */
    public function revokeConnectedApp(string $tokenId): void
    {
        $token = Auth::user()->tokens()->where('id', $tokenId)->first();

        if (! $token) {
            $this->successMessage = 'Esa aplicación ya no estaba conectada.';

            return;
        }

        $name = $token->client?->name ?? 'La aplicación';
        $token->revoke();
        $token->refreshToken?->revoke();

        $this->successMessage = "{$name} ya no tiene acceso a tu cuenta.";
    }

    /** Cierra otra sesión del usuario; la actual se termina con «Cerrar sesión». */
    public function closeSession(string $sessionId): void
    {
        if ($sessionId === session()->getId()) {
            return;
        }

        DB::table('sessions')->where('user_id', Auth::id())->where('id', $sessionId)->delete();
        $this->successMessage = 'Sesión cerrada.';
    }

    public function showTool(string $name): void
    {
        $this->selectedTool = $name;
        $this->toolOpen = true;
    }

    public function closeTool(): void
    {
        $this->toolOpen = false;
        $this->selectedTool = null;
    }

    public function removeToolFilter(string $key): void
    {
        if (in_array($key, ['toolKind', 'toolModule'], true)) {
            $this->reset($key);
        }
    }

    public function clearToolFilters(): void
    {
        $this->reset('toolSearch', 'toolKind', 'toolModule');
    }

    public function render()
    {
        return view('livewire.settings.settings-page', match ($this->section) {
            'body' => $this->bodyData(),
            'security' => ['sessions' => $this->sessions()],
            'oauth' => $this->oauthData(),
            'integrations' => $this->integrationsData(),
            'mcp' => $this->mcpData(),
            'logs' => ['logs' => $this->logsData()],
            'about' => ['about' => $this->aboutData()],
            default => [],
        });
    }

    private function bodyData(): array
    {
        $user = Auth::user();
        $bmi = $user->height_cm && $user->current_weight_kg
            ? round($user->current_weight_kg / (($user->height_cm / 100) ** 2), 1)
            : null;

        return ['age' => $user->birth_date?->age, 'bmi' => $bmi, 'waterGoal' => $user->daily_water_goal];
    }

    private function sessions(): Collection
    {
        if (config('session.driver') !== 'database') {
            return collect();
        }

        return DB::table('sessions')
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => (object) [
                'id' => $row->id,
                'device' => $this->deviceLabel((string) $row->user_agent),
                'ip' => $row->ip_address,
                'lastActivity' => Carbon::createFromTimestamp($row->last_activity),
                'current' => $row->id === session()->getId(),
            ]);
    }

    private function deviceLabel(string $agent): string
    {
        $browser = collect(['Edg' => 'Edge', 'OPR' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'])
            ->first(fn ($label, $needle) => str_contains($agent, $needle), 'Navegador');
        $os = collect(['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux'])
            ->first(fn ($label, $needle) => str_contains($agent, $needle), 'otro sistema');

        return "{$browser} en {$os}";
    }

    private function activeToken(string $purpose): ?IntegrationToken
    {
        return IntegrationToken::query()
            ->where('user_id', Auth::id())
            ->where('purpose', $purpose)
            ->whereNull('revoked_at')
            ->latest('created_at')
            ->first();
    }

    private function oauthData(): array
    {
        return [
            'activeAiToken' => $this->activeToken('ai'),
            'connectedApps' => Auth::user()->tokens()
                ->with('client')
                ->where('revoked', false)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest('created_at')
                ->get()
                ->filter(fn (Token $token) => $token->client !== null),
        ];
    }

    private function integrationsData(): array
    {
        return [
            'activeObsidianToken' => $this->activeToken('obsidian'),
            'activeCalDavToken' => $this->activeToken('caldav'),
            'calDavUrl' => rtrim(config('app.url'), '/').'/dav/',
        ];
    }

    private function mcpData(): array
    {
        $catalog = app(ToolCatalog::class);
        $all = collect($catalog->tools());
        $search = mb_strtolower(trim($this->toolSearch));

        $tools = $all
            ->when($search !== '', fn ($tools) => $tools->filter(fn ($tool) => str_contains(
                mb_strtolower($tool['name'].' '.$tool['moduleLabel'].' '.$tool['description']), $search)))
            ->when($this->toolKind !== '', fn ($tools) => $tools->where('readOnly', $this->toolKind === 'read'))
            ->when($this->toolModule !== '', fn ($tools) => $tools->where('module', $this->toolModule))
            ->values();

        return [
            'mcpUrl' => rtrim(config('app.url'), '/').'/miso',
            'tools' => $tools,
            'toolTotal' => $all->count(),
            'moduleCounts' => $all->countBy('module'),
            'promptCount' => $catalog->promptCount(),
            'resourceCount' => $catalog->resourceCount(),
            'instructions' => $catalog->instructions(),
            'tool' => $this->selectedTool ? $catalog->find($this->selectedTool) : null,
        ];
    }

    /** Datos del visor de logs: solo se muestran a cuentas autorizadas. */
    private function logsData(): array
    {
        $file = storage_path('logs/laravel.log');
        $exists = File::exists($file);

        return [
            'available' => (bool) config('log-viewer.enabled', true) && Route::has('log-viewer.index'),
            'path' => storage_path('logs'),
            'file' => $exists ? basename($file) : null,
            'size' => $exists ? File::size($file) : null,
            'modifiedAt' => $exists ? Carbon::createFromTimestamp(File::lastModified($file)) : null,
            'version' => InstalledVersions::isInstalled('opcodesio/log-viewer')
                ? InstalledVersions::getPrettyVersion('opcodesio/log-viewer')
                : null,
        ];
    }

    private function aboutData(): array
    {
        $connection = DB::connection();
        try {
            $dbVersion = $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION);
        } catch (\Throwable) {
            $dbVersion = null;
        }

        $package = fn (string $name) => InstalledVersions::isInstalled($name) ? InstalledVersions::getPrettyVersion($name) : '—';

        return [
            ['MiSo', config('app.version'), 'bi-activity'],
            ['Laravel', app()->version(), 'bi-box'],
            ['PHP', PHP_VERSION, 'bi-filetype-php'],
            ['Livewire', $package('livewire/livewire'), 'bi-lightning'],
            ['laravel/mcp', $package('laravel/mcp'), 'bi-robot'],
            ['Base de datos', trim($connection->getDriverName().' '.$dbVersion), 'bi-database'],
            ['Entorno', app()->environment().' · '.config('app.timezone'), 'bi-hdd-network'],
        ];
    }
}
