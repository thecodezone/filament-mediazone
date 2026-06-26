<?php

declare(strict_types=1);

namespace Codezone\MediaZone\Http\Controllers;

use Codezone\MediaZone\Services\GlideServerFactory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use League\Glide\Signatures\SignatureException;
use League\Glide\Signatures\SignatureFactory;

class MediaGlideController extends Controller
{
    public function show(Request $request, string $path)
    {
        try {
            SignatureFactory::create(config('app.key'))->validateRequest(
                '/'.config('media.glide.route_path', 'media').'/'.$path,
                $request->all()
            );
        } catch (SignatureException $e) {
            abort(403);
        }

        $server = app(GlideServerFactory::class)->getFactory();

        return $server->getImageResponse($path, $request->all());
    }
}
