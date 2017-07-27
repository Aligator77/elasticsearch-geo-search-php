<?php

use Elasticsearch\ClientBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app["elasticsearch.client"] = function () {
    return ClientBuilder::create()->build();
};

$app->get('/', function ($name = "Map") use ($app) {
    return 'Hello ' . $app->escape($name);
});

$app->get('/search/geo_bounding_box', function () use ($app) {
    $query = [
        'index' => 'map',
        'type' => 'location',
        'body' => [
            "query" => [
                "filtered" => [
                    "filter" => [
                        "geo_bounding_box" => [
                            "point" => [
                                "top_left" => [
                                    "lat" => -34.36,
                                    "lon" => 172.92
                                ],
                                "bottom_right" => [
                                    "lat" => -38.256,
                                    "lon" => 178.9
                                ],
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    return new JsonResponse($app['elasticsearch.client']->search($query));
});

$app->get('/search/geo_shape', function () use ($app) {
    $query = [
        'index' => 'map',
        'type' => 'location',
        'body' => [
            "query" => [
                "geo_shape" => [
                    "shape" => [
                        "relation" => "within",
                        "shape" => [
                            "type" => "circle",
                            "radius" => "1km",
                            "coordinates" => [
                                4.89994,
                                52.37815
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    return new JsonResponse($app['elasticsearch.client']->search($query));
});

$app->get('/add', function (Request $request) use ($app) {
    $lat = (float)$request->query->get('lat', 12.3121);
    $lon = (float)$request->query->get('lon', 52.3121);
    $desc = $request->query->get('desc', "another point");
    if ($app["elasticsearch.client"]->indices()->exists(['index' => 'map'])) {
        $params = [
            'index' => 'map',
            'type' => 'location',
            'body' => [
                'description' => $desc,
                'point' => [
                    'lat' => $lat,
                    'lon' => $lon
                ],
                'shape' => [
                    'type' => 'polygon',
                    'coordinates' => [[
                        [4.89218, 52.37356],
                        [4.89205, 52.37276],
                        [4.89301, 52.37274],
                        [4.89392, 52.37250],
                        [4.89431, 52.37287],
                        [4.89331, 52.37346],
                        [4.89305, 52.37326],
                        [4.89218, 52.37356]
                    ]]
                ]
            ]
        ];
        $app['elasticsearch.client']->index($params);
        $message = 'New document added to index "map"';
    } else {
        $message = 'Index "map" does not exist';
    }

    return $message;
});

$app->get('/drop', function () use ($app) {
    if ($app["elasticsearch.client"]->indices()->exists(['index' => 'map'])) {
        $app["elasticsearch.client"]->indices()->delete(['index' => 'map']);
        $message = 'Index "map" deleted';
    } else {
        $message = 'Index "map" does not exist';
    }

    return $message;
});

$app->get('/create', function () use ($app) {
    if ($app["elasticsearch.client"]->indices()->exists(['index' => 'map'])) {
        $message = 'Index "map" already exists.';
    } else {
        $params = [
            'index' => 'map',
            'body' => [
                'settings' => [
                    'number_of_shards' => 2,
                    'number_of_replicas' => 0
                ],
                'mappings' => [
                    'location' => [
                        '_source' => [
                            'enabled' => true
                        ],
                        'properties' => [
                            'point' => [
                                'type' => 'geo_point',
                                'index' => 'not_analyzed',
                                'lat_lon' => true
                            ],
                            'shape' => [
                                'type' => 'geo_shape',
                            ],
                            'description' => [
                                'type' => 'string',
                                'index' => 'not_analyzed'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $app["elasticsearch.client"]->indices()->create($params);
        $message = 'Index "map" created.';
    }

    return $message;
});

$app->run();