<?php

namespace OrchestrateXR\SuperBotMan\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OrchestrateXR\SuperBotMan\Facades\SuperBotMan;

/**
 * Serves the one-line embed snippet's loader: a small JavaScript file
 * that sets window.superbotmanWidget (a pruned, public, cacheable
 * config — absolute frame URLs, geometry, nothing session-derived and
 * nothing secret) and injects widget.js. Everything stateful happens
 * later, inside the frames the loader creates.
 *
 * 404s until the host implements SuperBotManConfigurator::embedContext()
 * and the key resolves — the package is inert unless a host opts in.
 */
class EmbedController
{
    public function __invoke(Request $request, string $key): Response
    {
        if (SuperBotMan::embedContext($key) === null) {
            abort(404);
        }

        $body = SuperBotMan::view('embed', [
            'config' => SuperBotMan::getEmbedLoaderConfig($key),
            'script' => SuperBotMan::asset('resources/js/widget.js'),
        ])->render();

        return new Response($body, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
