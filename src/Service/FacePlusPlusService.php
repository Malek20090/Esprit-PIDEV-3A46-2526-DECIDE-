<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FacePlusPlusService
{
    private HttpClientInterface $client;
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;
    private float $minConfidence;

    public function __construct(
        HttpClientInterface $client,
        string $apiKey,
        string $apiSecret,
        string $baseUrl,
        float $minConfidence
    ) {
        $this->client = $client;
        $this->apiKey = trim($apiKey);
        $this->apiSecret = trim($apiSecret);
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->minConfidence = $minConfidence;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiSecret !== '' && $this->baseUrl !== '';
    }

    public function getMinConfidence(): float
    {
        return $this->minConfidence;
    }

    /**
     * @return array{success: bool, token?: string, message?: string}
     */
    public function detectFaceToken(UploadedFile $image): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Face++ API is not configured on server.'];
        }

        $imageBase64 = $this->readImageAsBase64($image);
        if ($imageBase64 === null) {
            return ['success' => false, 'message' => 'Invalid image file.'];
        }

        $data = $this->call('/facepp/v3/detect', [
            'image_base64' => $imageBase64,
            'return_landmark' => '0',
            'return_attributes' => '',
        ]);
        if (!$data['success']) {
            return $data;
        }

        $faces = $data['payload']['faces'] ?? null;
        if (!is_array($faces) || count($faces) !== 1 || !isset($faces[0]['face_token'])) {
            return ['success' => false, 'message' => 'Please provide a clear photo with exactly one face.'];
        }

        return ['success' => true, 'token' => (string) $faces[0]['face_token']];
    }

    /**
     * @return array{success: bool, confidence?: float, message?: string}
     */
    public function compareWithStoredToken(UploadedFile $image, string $storedToken): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => 'Face++ API is not configured on server.'];
        }

        $storedToken = trim($storedToken);
        if ($storedToken === '') {
            return ['success' => false, 'message' => 'No stored face token for this account.'];
        }

        $imageBase64 = $this->readImageAsBase64($image);
        if ($imageBase64 === null) {
            return ['success' => false, 'message' => 'Invalid image file.'];
        }

        $data = $this->call('/facepp/v3/compare', [
            'image_base64_1' => $imageBase64,
            'face_token2' => $storedToken,
        ]);
        if (!$data['success']) {
            return $data;
        }

        $confidence = $data['payload']['confidence'] ?? null;
        if (!is_numeric($confidence)) {
            return ['success' => false, 'message' => 'Face++ did not return a confidence score.'];
        }

        return ['success' => true, 'confidence' => (float) $confidence];
    }

    private function readImageAsBase64(UploadedFile $image): ?string
    {
        if (!$image->isValid()) {
            return null;
        }

        $mime = (string) ($image->getMimeType() ?? '');
        if ($mime !== '' && strncmp($mime, 'image/', 6) !== 0) {
            return null;
        }

        $path = $image->getPathname();
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return null;
        }

        return base64_encode($content);
    }

    /**
     * @param array<string, string> $params
     * @return array{success: bool, payload?: array<string, mixed>, message?: string}
     */
    private function call(string $path, array $params): array
    {
        try {
            $response = $this->client->request('POST', $this->baseUrl . $path, [
                'body' => array_merge([
                    'api_key' => $this->apiKey,
                    'api_secret' => $this->apiSecret,
                ], $params),
                'timeout' => 20,
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'DecideApp/1.0',
                ],
            ]);

            $payload = $response->toArray(false);
            if (!is_array($payload)) {
                return ['success' => false, 'message' => 'Face++ returned an invalid response.'];
            }

            if (isset($payload['error_message'])) {
                return ['success' => false, 'message' => (string) $payload['error_message']];
            }

            return ['success' => true, 'payload' => $payload];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Face++ request failed: ' . $e->getMessage()];
        }
    }
}
