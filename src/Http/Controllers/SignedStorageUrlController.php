<?php

namespace App\Http\Controllers;

use Storage;
use Aws\S3\S3Client;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class SignedStorageUrlController extends Controller
{
    public $disk;
    public $disk_name;

    public function __construct() {
        $this->disk_name = request()->disk ?? config('media-library.disk_name');
        $this->disk = config('filesystems.disks.' . $this->disk_name);
    }
    /**
     * Create a new signed URL.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $bucket = $this->disk['bucket'];

        $client = $this->storageClient();

        $uuid = (string) Str::uuid();

        $signedRequest = $client->createPresignedRequest(
            $this->createCommand($request, $client, $bucket, $this->disk['root'] . $key = ('/tmp/' . $uuid)),
            '+5 minutes'
        );

        $uri = $signedRequest->getUri();

        return response()->json([
            'uuid' => $uuid,
            'bucket' => $bucket,
            'key' => $key,
            'url' => 'https://'.$uri->getHost().$uri->getPath().'?'.$uri->getQuery(),
            'headers' => $this->headers($request, $signedRequest),
        ], 201);
    }

    /**
     * Create a command for the PUT operation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Aws\S3\S3Client  $client
     * @param  string  $bucket
     * @param  string  $key
     * @return \Aws\Command
     */
    protected function createCommand(Request $request, S3Client $client, $bucket, $key)
    {
        return $client->getCommand('putObject', array_filter([
            'Bucket' => $bucket,
            'Key' => $key,
            'ACL' => $this->disk['visibility'], //$this->defaultVisibility(),
            'ContentType' => $request->input('content_type') ?: 'application/zip',
            'CacheControl' => $request->input('cache_control') ?: null,
            'Expires' => $request->input('expires') ?: null,
        ]));
    }

    protected function headers(Request $request, $signedRequest)
    {
        return array_merge(
            $signedRequest->getHeaders(),
            [
                'Content-Type' => $request->input('content_type') ?: 'application/zip'
            ]
        );
    }


    protected function ensureEnvironmentVariablesAreAvailable(Request $request)
    {
        $missing = array_diff_key(array_flip(array_filter([
            $request->input('bucket') ? null : 'AWS_BUCKET',
            'AWS_DEFAULT_REGION',
            'AWS_ACCESS_KEY_ID',
            'AWS_SECRET_ACCESS_KEY'
        ])), $_ENV);

        if (empty($missing)) {
            return;
        }

        throw new InvalidArgumentException(
            "Unable to issue signed URL. Missing environment variables: ".implode(', ', array_keys($missing))
        );
    }

    /**
     * Get the S3 storage client instance.
     *
     * @return \Aws\S3\S3Client
     */
    protected function storageClient()
    {
        $config = [
            'region' => '',
            'version' => 'latest',
            'signature_version' => 'v4',
            'endpoint' => $this->disk['endpoint'] . '/' .  $this->disk['region'] . data_get($this->disk, 'root'),
            'credentials' => [
                'key'    => env('DIGITALOCEAN_SPACES_KEY'),
                'secret' => env('DIGITALOCEAN_SPACES_SECRET'),
            ],
        ];

        return S3Client::factory($config);
    }

    /**
     * Get the default visibility for uploads.
     *
     * @return string
     */
    protected function defaultVisibility()
    {
        return 'public-read';
        // return 'private';
    }
    public function createImage(\Laravel\Nova\Http\Requests\NovaRequest $request)
    {
        $request = request();
        $order_column = 1;
        $model_id = 0;
        $nova_class = \Laravel\Nova\Nova::resourceForKey($request->class);
        $model_type = get_class((new $nova_class($nova_class::newModel()))->resource);

        $name = $request->name;
        $url = $request->url;
        $collection_name = $request->collection_name ?? '';

        $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::create([
            'collection_name' => $collection_name,
            'name' => $name,
            'file_name' => $name,
            'order_column' => $order_column,
            'model_id' => $model_id,
            'model_type' => $model_type,
            'disk' => $this->disk_name,
            'conversions_disk' => $this->disk_name,
            'responsive_images' => [],
            'manipulations' => [],
            'mime_type' => $request->type,
            'size' => $request->size,
            'custom_properties' => [],
        ]);

        Storage::disk($this->disk_name)
            ->move(
                $request->url,
                "$media->id/$name"
            );

        Artisan::call('media-library:regenerate', [
            '--ids' => $media->id,
        ]);

        return [
            'media' => $media,
            'id' => $media->id,
            'url' => $media->getTemporaryUrl(now()->addMinutes(5)),
        ];
    }
}
