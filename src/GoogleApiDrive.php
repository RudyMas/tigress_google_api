<?php

namespace Tigress;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Drive\Permission;
use Google\Service\Exception;

/**
 * Class GoogleApiDrive (PHP version 8.4)
 *
 * @author Rudy Mas <rudy.mas@rudymas.be>
 * @copyright 2024-2025, rudymas.be. (http://www.rudymas.be/)
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @version 2025.01.14.0
 * @package Tigress\GoogleApiDrive
 */
class GoogleApiDrive extends GoogleApiAuth
{
    /**
     * Get the version of the class.
     *
     * @return string
     */
    public static function version(): string
    {
        return '2025.01.14';
    }

    /**
     * Set up the connection with the Google Drive API.
     *
     * @param string $authConfigPath
     * @param string $credentialsPath
     * @return void
     */
    public function config(string $authConfigPath, string $credentialsPath = ''): void
    {
        $this->setAuthConfigPath($authConfigPath);
        $this->setCredentialsPath($credentialsPath);
    }

    /**
     * Execute the upload file request for posted files.
     *
     * @param string $folderId
     * @param string $permission
     * @param string $postName
     * @param string $fileName
     * @param string|null $userAccount
     * @return array|null
     * @throws Exception
     */
    public function uploadFile(
        string $folderId,
        string $permission = 'reader',
        string $postName = 'upload',
        string $fileName = '',
        ?string $userAccount = null
    ): ?array
    {
        if (is_array($_FILES[$postName]['name'])) {
            if (count($_FILES[$postName]['name']) > 0) {
                $files = [];
                $fileNames = [];
                for ($i = 0; $i < count($_FILES[$postName]['name']); $i++) {
                    $tmpFilePath = $_FILES[$postName]['tmp_name'][$i];
                    if ($tmpFilePath != "") {
                        $files[$i] = $tmpFilePath;
                        if (empty($fileName)) {
                            $fileNames[$i] = $_FILES[$postName]['name'][$i];
                        } else {
                            $fileNames[$i] = sprintf('%02d', $i) . '_' . $fileName;
                        }
                    }
                }

                $webLinks = [];
                for ($j = 0; $j < count($files); $j++) {
                    $fileName = $fileNames[$j];
                    $contents = $files[$j];
                    $mimeType = mime_content_type($files[$j]);

                    $webLink = $this->uploadGoogle($fileName, $folderId, $contents, $mimeType, $permission, $userAccount);

                    $webLinks[] = [
                        'fileName' => $fileName,
                        'webLink' => $webLink
                    ];
                }
                return $webLinks;
            }
        } else {
            if (!empty($_FILES[$postName]['name'])) {
                if (empty($fileName)) {
                    $fileName = $_FILES[$postName]['name'];
                }

                $contents = $_FILES[$postName]['tmp_name'];
                $mimeType = mime_content_type($_FILES[$postName]['tmp_name']);

                $webLink = $this->uploadGoogle($fileName, $folderId, $contents, $mimeType, $permission, $userAccount);

                $webLinks[] = [
                    'fileName' => $fileName,
                    'webLink' => $webLink
                ];

                return $webLinks;
            }
        }
        return null;
    }

    /**
     * Execute the upload file request to Google Drive.
     *
     * @param $fileName
     * @param string $folderId
     * @param $contents
     * @param string $mimeType
     * @param string $permission
     * @param string|null $userAccount
     * @return string
     * @throws Exception
     */
    public function uploadGoogle(
        $fileName,
        string $folderId,
        $contents,
        string $mimeType,
        string $permission,
        ?string $userAccount = null
    ): string
    {
        $service = new Drive($this->client);
        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
            'mimeType' => $mimeType
        ]);

        $file = $service->files->create($fileMetadata, [
            'data' => file_get_contents($contents),
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);

        if (is_null($userAccount)) {
            $userPermission = new Permission([
                'type' => 'anyone',
                'role' => $permission
            ]);
        } else {
            $userPermission = new Permission([
                'type' => 'user',
                'role' => $permission,
                'emailAddress' => $userAccount
            ]);
        }

        $service->permissions->create($file->id, $userPermission, ['fields' => 'id']);

        $file = $service->files->get($file->id, ['fields' => 'webViewLink']);
        return $file->webViewLink;
    }

    /**
     * Execute the copy file request to Google Drive.
     *
     * @param $template
     * @param $fileName
     * @param string $folderId
     * @param string|null $userAccount
     * @param string $mimeType
     * @param string $permission
     * @return string
     * @throws Exception
     */
    public function copyGoogle(
        $template,
        $fileName,
        string $folderId,
        ?string $userAccount = null,
        string $mimeType = 'application/vnd.google-apps.document',
        string $permission = 'reader'
    ): string
    {
        $service = new Drive($this->client);

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
            'mimeType' => $mimeType
        ]);

        $file = $service->files->copy($template, $fileMetadata, [
            'fields' => 'id'
        ]);

        if (is_null($userAccount)) {
            $userPermission = new Permission([
                'type' => 'anyone',
                'role' => $permission
            ]);
        } else {
            $userPermission = new Permission([
                'type' => 'user',
                'role' => $permission,
                'emailAddress' => $userAccount
            ]);
        }

        $service->permissions->create($file->id, $userPermission, ['fields' => 'id']);

        $file = $service->files->get($file->id, ['fields' => 'webViewLink']);
        return $file->webViewLink;
    }

    /**
     * Execute the delete file request to Google Drive.
     *
     * @param string $googleFileId
     * @return void
     * @throws Exception
     */
    public function deleteGoogle(string $googleFileId): void
    {
        $service = new Drive($this->client);
        $service->files->delete($googleFileId);
    }

    /**
     * Execute the create PDF request to Google Drive.
     *
     * @param string $googleFileId
     * @param string $folderId
     * @param string|null $userAccount
     * @param string $permission
     * @return string
     * @throws Exception
     */
    public function createPDF(
        string  $googleFileId,
        string  $folderId,
        ?string $userAccount = null,
        string  $permission = 'reader'
    ): string
    {
        $service = new Drive($this->client);
        $file = $service->files->get($googleFileId);
        $fileName = $file->getName();

        $file = $service->files->export($googleFileId, 'application/pdf', [
            'alt' => 'media'
        ]);
        $content = $file->getBody()->getContents();

        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
            'mimeType' => 'application/pdf'
        ]);

        $file = $service->files->create($fileMetadata, [
            'data' => $content,
            'mimeType' => 'application/pdf',
            'uploadType' => 'multipart',
            'fields' => 'id'
        ]);

        $fileId = $file->id;

        if (is_null($userAccount)) {
            $userPermission = new Permission([
                'type' => 'anyone',
                'role' => $permission
            ]);
        } else {
            $userPermission = new Permission([
                'type' => 'user',
                'role' => $permission,
                'emailAddress' => $userAccount
            ]);
        }

        $request = $service->permissions->create(
            $fileId,
            $userPermission,
            ['fields' => 'id']
        );

        $file = $service->files->get($fileId, ['fields' => 'webViewLink']);
        return $file->webViewLink;
    }
}