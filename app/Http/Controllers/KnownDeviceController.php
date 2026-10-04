<?php

namespace App\Http\Controllers;

use App\Models\KnownDevice;
use App\Models\User;
use App\Services\DeviceRecognizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * F54 : « Ce n'était pas moi ».
 *
 * - Depuis l'application (groupe auth) : confirmation (GET) puis action (POST, CSRF), Policy « report ».
 * - Depuis l'e-mail (route publique décidée) : URL signée temporaire liée à l'appareil et à son propriétaire.
 *   L'ouverture du lien (GET) affiche une confirmation ; seule la validation (POST) agit, pour qu'un scanner
 *   de liens de messagerie ne déclenche rien tout seul.
 * Aucune donnée de la requête n'est enregistrée : les champs envoyés (user_id, revoked_at…) sont ignorés.
 */
class KnownDeviceController extends Controller
{
    public function __construct(private DeviceRecognizer $recognizer) {}

    public function confirm(KnownDevice $knownDevice): View
    {
        Gate::authorize('report', $knownDevice);

        return view('pages::profile.device-report', [
            'device' => $knownDevice,
            'action' => route('profile.devices.not-me', $knownDevice),
        ]);
    }

    public function notMe(Request $request, KnownDevice $knownDevice): RedirectResponse
    {
        Gate::authorize('report', $knownDevice);

        /** @var User $user */
        $user = $request->user();
        $this->recognizer->reportNotMe($user, $knownDevice, $request->session()->getId());

        return $this->redirectAfterReport($request, connecte: true);
    }

    public function showSigned(Request $request, KnownDevice $knownDevice): View
    {
        $this->ensureLinkMatches($request, $knownDevice);

        return view('pages::profile.device-report', [
            'device' => $knownDevice,
            // Le formulaire renvoie vers la même URL signée (signature et expiration incluses).
            'action' => $request->fullUrl(),
        ]);
    }

    public function reportSigned(Request $request, KnownDevice $knownDevice): RedirectResponse
    {
        $this->ensureLinkMatches($request, $knownDevice);

        $connecte = $request->user() !== null;
        $this->recognizer->reportNotMe($knownDevice->user, $knownDevice, $connecte ? $request->session()->getId() : null);

        return $this->redirectAfterReport($request, $connecte);
    }

    /**
     * Le lien doit désigner le propriétaire de l'appareil ; connecté sous un autre compte → 403.
     */
    private function ensureLinkMatches(Request $request, KnownDevice $knownDevice): void
    {
        abort_unless((string) $request->query('user') === (string) $knownDevice->user_id, 403, 'Ce lien ne correspond à aucun de vos appareils.');

        if ($request->user() !== null) {
            Gate::authorize('report', $knownDevice);
        }
    }

    private function redirectAfterReport(Request $request, bool $connecte): RedirectResponse
    {
        if ($connecte) {
            // Gardé en session (pas en flash) : la page sécurité peut d'abord demander la confirmation du mot de passe.
            $request->session()->put('appareil_signale', DeviceRecognizer::MESSAGE_SIGNALE);

            return redirect()->route('security.edit');
        }

        return redirect()->route('password.request')->with('status', DeviceRecognizer::MESSAGE_SIGNALE);
    }
}
