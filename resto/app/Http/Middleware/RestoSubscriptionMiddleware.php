<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RestoSubscriptionMiddleware
{
    protected $globalApiUrl;
    protected $expiredPageUrl;

    public function __construct()
    {
        $this->globalApiUrl = 'https://cokitana.ddnsfree.com/api/tenant/check-by-subdomain';
        $this->expiredPageUrl = 'https://cokitana.ddnsfree.com/tenant/subscription-expired';
    }

    public function handle(Request $request, Closure $next)
    {
        // Extraire le subdomain de l'URL
        $subdomain = $this->extractSubdomain($request->getHost());
        
        if (!$subdomain) {
            Log::warning('RestoSubscriptionMiddleware: Impossible d\'extraire le subdomain', [
                'host' => $request->getHost(),
                'url' => $request->fullUrl()
            ]);
            return $next($request);
        }

        // Vérifier l'abonnement via l'API globale
        $subscriptionStatus = $this->checkSubscriptionFromGlobalAPI($subdomain);
        
        if ($subscriptionStatus === null) {
            Log::error('RestoSubscriptionMiddleware: Erreur lors de la vérification de l\'abonnement', [
                'subdomain' => $subdomain,
                'url' => $request->fullUrl()
            ]);
            return $next($request);
        }

        // Si l'abonnement est expiré, rediriger vers la page d'expiration
        if ($subscriptionStatus['is_expired']) {
            Log::info('RestoSubscriptionMiddleware: Abonnement expiré, redirection', [
                'subdomain' => $subdomain,
                'tenant_id' => $subscriptionStatus['tenant_id'],
                'expire_at' => $subscriptionStatus['subscription_expire_at']
            ]);
            
            return redirect()->away($this->expiredPageUrl);
        }

        // Ajouter les informations d'abonnement à la requête pour utilisation ultérieure
        $request->attributes->set('subscription_status', $subscriptionStatus);
        
        return $next($request);
    }

    private function extractSubdomain(string $host): ?string
    {
        // Supprimer le port si présent
        $host = preg_replace('/:\d+$/', '', $host);
        
        // Extraire le subdomain (première partie avant le premier point)
        $parts = explode('.', $host);
        
        if (count($parts) >= 2) {
            return $parts[0];
        }
        
        return null;
    }

    private function checkSubscriptionFromGlobalAPI(string $subdomain): ?array
    {
        try {
            $response = Http::timeout(10)
                ->post($this->globalApiUrl, [
                    'subdomain' => $subdomain
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['success']) && $data['success'] && isset($data['data'])) {
                    return $data['data'];
                }
            }
            
            Log::error('RestoSubscriptionMiddleware: Réponse API invalide', [
                'subdomain' => $subdomain,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            
        } catch (\Exception $e) {
            Log::error('RestoSubscriptionMiddleware: Exception lors de l\'appel API', [
                'subdomain' => $subdomain,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
}
