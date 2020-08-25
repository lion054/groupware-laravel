<?php

namespace App\Http\Middleware;

use Lcobucci\JWT\Parser;
use Lcobucci\JWT\ValidationData;

use Closure;

class AuthenticateToken
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
        $header = $request->header('Authorization');
        if (empty($header)) {
            return response()->json([
                'success' => false,
                'error' => 'Bad Request',
            ], 400);
        }

        $parser = new Parser();
        $token = $parser->parse($header);

        $data = new ValidationData();
        $data->setIssuer(config('jwt.iss'));
        $data->setAudience(config('jwt.aud'));

        if (!$token->validate($data)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
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
                'message' => 'Not Found',
            ], 404);
        }

        return $next($request);
    }
}
