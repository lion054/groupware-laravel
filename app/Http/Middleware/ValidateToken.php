<?php

namespace App\Http\Middleware;

use GraphAware\Neo4j\Client\ClientBuilder;
use Lcobucci\JWT\Claim\Validatable;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\ValidationData;

use Closure;

class ValidateToken
{
    /**
     * @var Neo4j PHP Client
     */
    protected $client = null;

    /**
     * Create a new middleware instance.
     */
    public function __construct()
    {
        $host = config('database.connections.neo4j.host');
        $port = config('database.connections.neo4j.port');
        $username = config('database.connections.neo4j.username');
        $password = config('database.connections.neo4j.password');

        $this->client = ClientBuilder::create()
            ->addConnection('default', "http://$username:$password@$host:$port")
            ->build();
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $value = $request->bearerToken();
        if (empty($value)) {
            return response()->json([
                'success' => false,
                'error' => 'Token not found',
            ], 401);
        }

        $parser = new Parser();
        $token = $parser->parse($value);

        $signer = new Sha256();
        $secret = config('jwt.secret');
        if (!$token->verify($signer, $secret)) {
            return response()->json([
                'success' => false,
                'error' => 'Token not verified',
            ], 401);
        }

        $leeway = config('jwt.leeway');
        $data = new ValidationData(null, $leeway);
        $data->setIssuer(config('jwt.iss'));
        $data->setAudience(config('jwt.aud'));

        $claims = $this->getValidatableClaims($token);
        foreach ($claims as $claim) {
            if ($claim->validate($data))
                continue;
            if ($claim->getName() == 'exp') {
                return response()->json([
                    'success' => false,
                    'error' => 'Token expired',
                ], 401);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid token data',
                ], 401);
            }
        }

        $query = [
            'MATCH (u:User{ uuid: {uuid} })',
            'RETURN u',
        ];
        $result = $this->client->run(implode(' ', $query), [
            'uuid' => $token->getClaim('sub'),
        ]);

        if ($result->size() == 0) {
            return response()->json([
                'success' => false,
                'error' => 'User not found',
            ], 404);
        }

        $user = $result->getRecord()->get('u')->values();
        if (isset($user['password']))
            unset($user['password']);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }

    private function getValidatableClaims(Token $token)
    {
        foreach ($token->getClaims() as $claim) {
            if ($claim instanceof Validatable)
                yield $claim;
        }
    }
}
