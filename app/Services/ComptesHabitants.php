<?php

namespace App\Services;

use App\Http\Middleware\DefinirLangue;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * F71 : comptes des habitants sans adresse e-mail.
 *
 * - Un agent crée le compte (un par un ou par import CSV) : identifiant HAB-XXXXXX + code d'activation imprimable.
 * - Le code d'activation n'est stocké que sous forme d'empreinte et ne sert qu'une fois : l'habitant choisit
 *   alors son code personnel (mot de passe) et se connecte avec son identifiant ou son téléphone.
 */
class ComptesHabitants
{
    /** Alphabet sans caractères ambigus (pas de 0/O, 1/I/L) : lisible une fois imprimé. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const MAX_LIGNES_CSV = 500;

    /**
     * @param  array{name: string, telephone?: string|null, langue?: string|null}  $donnees
     * @return array{user: User, code: string}
     */
    public function creer(array $donnees, ?string $motDePasseInutilisable = null): array
    {
        $code = $this->genererCode();
        $identifiant = $this->genererIdentifiant();

        $user = new User([
            'name' => $donnees['name'],
            'telephone' => User::normaliserTelephone($donnees['telephone'] ?? null),
        ]);

        // Champs réservés assignés dans le code : rôle, identifiant, adresse technique, code d'activation.
        $user->forceFill([
            'identifiant' => $identifiant,
            'email' => self::emailTechnique($identifiant),
            // Mot de passe aléatoire jamais communiqué : le compte ne s'ouvre qu'avec le code d'activation.
            'password' => $motDePasseInutilisable ?? Hash::make(Str::random(64)),
            'code_activation' => $this->empreinte($code),
            'langue' => $donnees['langue'] ?? null,
            'role_id' => Role::idFor(Role::CITOYEN),
        ])->save();

        return ['user' => $user, 'code' => $code];
    }

    /**
     * Nouveau code d'activation (l'ancien devient invalide), par exemple si l'habitant a perdu sa fiche.
     */
    public function nouveauCode(User $user): string
    {
        $code = $this->genererCode();

        if ($user->identifiant === null) {
            $user->forceFill(['identifiant' => $this->genererIdentifiant()]);
        }

        $user->forceFill(['code_activation' => $this->empreinte($code)])->save();

        return $code;
    }

    /**
     * Vérifie le code d'activation ; en cas de succès il est consommé (usage unique) et le code personnel enregistré.
     */
    public function activer(User $user, string $code, string $codePersonnel): bool
    {
        if ($user->code_activation === null || ! hash_equals($user->code_activation, $this->empreinte($code))) {
            return false;
        }

        $user->forceFill([
            'code_activation' => null,
            'password' => $codePersonnel,
        ])->save();

        return true;
    }

    /**
     * Import CSV (colonnes : nom ; téléphone ; langue — en-tête facultatif, séparateur « ; » ou « , »).
     *
     * @return array{crees: list<array{nom: string, identifiant: string, telephone: string|null, code: string}>, erreurs: list<string>}
     */
    public function importer(string $chemin): array
    {
        $lignes = $this->lireCsv($chemin);

        if (count($lignes) > self::MAX_LIGNES_CSV) {
            return ['crees' => [], 'erreurs' => ['Le fichier contient '.count($lignes).' lignes : '.self::MAX_LIGNES_CSV.' au maximum par import.']];
        }

        // Une seule empreinte pour tout l'import : 500 hachages bcrypt dépasseraient le temps d'exécution du serveur.
        $motDePasseInutilisable = Hash::make(Str::random(64));

        return DB::transaction(fn (): array => $this->creerDepuisLignes($lignes, $motDePasseInutilisable));
    }

    /**
     * @param  array<int, list<string>>  $lignes
     * @return array{crees: list<array{nom: string, identifiant: string, telephone: string|null, code: string}>, erreurs: list<string>}
     */
    private function creerDepuisLignes(array $lignes, string $motDePasseInutilisable): array
    {
        $crees = [];
        $erreurs = [];
        $telephonesVus = [];

        foreach ($lignes as $numero => $colonnes) {
            $donnees = [
                'name' => trim($colonnes[0] ?? ''),
                'telephone' => trim($colonnes[1] ?? '') ?: null,
                'langue' => strtolower(trim($colonnes[2] ?? '')) ?: null,
            ];

            $validation = Validator::make($donnees, [
                'name' => ['required', 'string', 'max:255'],
                'telephone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 .()-]{6,30}$/'],
                'langue' => ['nullable', 'in:'.implode(',', array_keys(DefinirLangue::LANGUES))],
            ]);

            if ($validation->fails()) {
                $erreurs[] = 'Ligne '.$numero.' : '.$validation->errors()->first();

                continue;
            }

            $telephone = User::normaliserTelephone($donnees['telephone']);

            if ($telephone !== null && (isset($telephonesVus[$telephone]) || User::where('telephone', $telephone)->exists())) {
                $erreurs[] = 'Ligne '.$numero.' : le numéro '.$telephone.' est déjà utilisé par un autre compte.';

                continue;
            }

            if ($telephone !== null) {
                $telephonesVus[$telephone] = true;
            }

            ['user' => $user, 'code' => $code] = $this->creer($donnees, $motDePasseInutilisable);

            $crees[] = ['nom' => $user->name, 'identifiant' => (string) $user->identifiant, 'telephone' => $user->telephone, 'code' => $code];
        }

        return ['crees' => $crees, 'erreurs' => $erreurs];
    }

    public static function emailTechnique(string $identifiant): string
    {
        return strtolower($identifiant).'@'.User::DOMAINE_SANS_EMAIL;
    }

    public function genererIdentifiant(): string
    {
        do {
            $identifiant = 'HAB-'.$this->aleatoire(6);
        } while (User::where('identifiant', $identifiant)->exists());

        return $identifiant;
    }

    /**
     * Code au format XXXX-XXXX (8 caractères parmi 31, environ 8.10^11 combinaisons).
     */
    private function genererCode(): string
    {
        return $this->aleatoire(4).'-'.$this->aleatoire(4);
    }

    /**
     * Empreinte HMAC (clé de l'application) : rapide à vérifier et inutilisable sans la clé.
     */
    private function empreinte(string $code): string
    {
        $normalise = strtoupper((string) preg_replace('/[^a-z0-9]/i', '', $code));

        return hash_hmac('sha256', $normalise, (string) config('app.key'));
    }

    private function aleatoire(int $longueur): string
    {
        $resultat = '';

        for ($i = 0; $i < $longueur; $i++) {
            $resultat .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $resultat;
    }

    /**
     * @return array<int, list<string>> lignes indexées par leur numéro dans le fichier
     */
    private function lireCsv(string $chemin): array
    {
        $contenu = (string) file_get_contents($chemin);
        $contenu = (string) preg_replace('/^\xEF\xBB\xBF/', '', $contenu);
        $lignesBrutes = preg_split('/\r\n|\r|\n/', $contenu) ?: [];
        $premiere = $lignesBrutes[0] ?? '';
        $separateur = substr_count($premiere, ';') >= substr_count($premiere, ',') ? ';' : ',';

        $lignes = [];

        foreach ($lignesBrutes as $index => $ligne) {
            if (trim($ligne) === '') {
                continue;
            }

            $colonnes = array_map(fn (?string $valeur): string => (string) $valeur, str_getcsv($ligne, $separateur, '"', ''));

            // En-tête facultatif : « nom ; telephone ; langue ».
            if ($index === 0 && in_array(Str::lower(Str::ascii(trim($colonnes[0]))), ['nom', 'name', 'nom complet'], true)) {
                continue;
            }

            $lignes[$index + 1] = $colonnes;
        }

        return $lignes;
    }
}
