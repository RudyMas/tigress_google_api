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
 * @version 2025.11.24.0
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
        return '2025.11.24';
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
     * Execute the copy file request to Google Drive.
     *
     * @param string $template
     * @param string $fileName
     * @param string $folderId
     * @param string|null $userAccount
     * @param string $mimeType
     * @param string $permission
     * @return string
     * @throws Exception
     */
    public function copyGoogle(
        string $template,                 // source fileId (can be a shortcut)
        string $fileName,
        string $folderId,                 // destination folderId
        ?string $userAccount = null,      // if null -> link for anyone, else share to this user
        string $mimeType = 'application/vnd.google-apps.document',
        string $permission = 'reader'     // 'reader' | 'commenter' | 'writer'
    ): string
    {
        $service = new Drive($this->client);

        // For the initial source lookup (may be a shortcut)
        $src = $service->files->get($template, [
            'fields' => 'id,name,mimeType,trashed,shortcutDetails,driveId,capabilities',
            'supportsAllDrives' => true,
        ]);

        // If it's a shortcut, follow it
        if ($src->getMimeType() === 'application/vnd.google-apps.shortcut') {
            $targetId = $src->getShortcutDetails()->getTargetId();
            if (!$targetId) {
                throw new Exception('Shortcut has no targetId.');
            }
            $src = $service->files->get($targetId, [
                'fields' => 'id,name,mimeType,trashed,driveId,capabilities',
                'supportsAllDrives' => true,
            ]);
        }

        if ($src->getTrashed()) {
            throw new Exception('Source file is in the trash.');
        }
        if ($src->getCapabilities() && $src->getCapabilities()->canCopy === false) {
            throw new Exception('You do not have permission to copy this file.');
        }

        // Validate destination folder
        $dest = $service->files->get($folderId, [
            'fields' => 'id,mimeType,trashed,driveId,capabilities',
            'supportsAllDrives' => true,
        ]);

        if ($dest->getTrashed()) {
            throw new Exception('Destination folder is in the trash.');
        }
        if ($dest->getMimeType() !== 'application/vnd.google-apps.folder') {
            throw new Exception('Destination is not a folder.');
        }
        if ($dest->getCapabilities() && $dest->getCapabilities()->canAddChildren === false) {
            throw new Exception('No permission to add files to the destination folder.');
        }

        // Do the copy (mimeType in copy body is ignored for Google files; harmless)
        $fileMetadata = new DriveFile([
            'name' => $fileName,
            'parents' => [$folderId],
            // Setting mimeType here does nothing for Google Docs; kept for parity with your signature
            'mimeType' => $mimeType,
        ]);

        $copied = $service->files->copy($src->getId(), $fileMetadata, [
            'fields' => 'id,name,parents,webViewLink',
            'supportsAllDrives' => true,
        ]);

        // Optional sharing
        if ($userAccount === null) {
            // Public link (anyone with link)
            $perm = new Permission([
                'type' => 'anyone',
                'role' => $permission,
            ]);
        } else {
            // Share to a specific user
            $perm = new Permission([
                'type' => 'user',
                'role' => $permission,
                'emailAddress' => $userAccount,
            ]);
        }

        // Note: includeItemsFromAllDrives is not needed for permissions, but harmless
        $service->permissions->create($copied->id, $perm, [
            'fields' => 'id',
            'sendNotificationEmail' => false,
            'supportsAllDrives' => true,
        ]);

        // Return the webViewLink
        // (Ask for it in the copy call already; re-get only if missing)
        if (!$copied->getWebViewLink()) {
            $copied = $service->files->get($copied->id, [
                'fields' => 'webViewLink',
                'supportsAllDrives' => true,
            ]);
        }

        return $copied->getWebViewLink();
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
            'fields' => 'id',
            'supportsAllDrives' => true,
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

        $file = $service->files->get($fileId, [
            'fields' => 'webViewLink',
            'supportsAllDrives' => true,
        ]);
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

        try {
            // 1) Get the file metadata (handles shortcuts)
            $file = $service->files->get($googleFileId, [
                'fields' => 'id,name,mimeType,trashed,shortcutDetails',
                'supportsAllDrives' => true,
            ]);

            // Resolve shortcut → target
            if ($file->getMimeType() === 'application/vnd.google-apps.shortcut') {
                $targetId = $file->getShortcutDetails()->getTargetId();
                if ($targetId) {
                    $file = $service->files->get($targetId, [
                        'fields' => 'id,name,mimeType,trashed',
                        'supportsAllDrives' => true,
                    ]);
                }
            }

            // 2) If already trashed, permanently delete it
            if ($file->getTrashed()) {
                $service->files->delete($file->getId(), [
                    'supportsAllDrives' => true,
                ]);
                return;
            }

            // 3) Otherwise, move to trash first
            $service->files->update($file->getId(), new \Google\Service\Drive\DriveFile([
                'trashed' => true,
            ]), [
                'supportsAllDrives' => true,
            ]);

            // 4) Optional: permanently delete (uncomment if you always want that)
            // $service->files->delete($file->getId(), [
            //     'supportsAllDrives' => true,
            // ]);

        } catch (\Google\Service\Exception $e) {
            if ($e->getCode() === 404) {
                throw new Exception('File not found or access denied.');
            }
            throw new Exception('Google Drive API error: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new Exception('Delete operation failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute the list files request for a specific folder.
     *
     * @param string $folderId
     * @param string $fields
     * @param string $orderBy
     * @param string $query
     * @param bool   $includeFolders
     * @param bool   $includeShortcuts
     * @return array
     * @throws Exception
     */
    public function listFiles(
        string $folderId,
        string $fields = 'id, iconLink, name, size, mimeType, createdTime, modifiedTime, webViewLink, description',
        string $orderBy = 'folder,name',
        string $query = '',
        bool   $includeFolders = false,
        bool   $includeShortcuts = false,
    ): array
    {
        $service = new Drive($this->client);

        // Build query
        $q = !empty($query) ? $query : sprintf("'%s' in parents and trashed = false", $folderId);

        // Extract the field names to build the output array
        $keys = [];
        preg_match_all('/\b(\w+)\b/', $fields, $matches);
        if (isset($matches[1])) {
            $keys = $matches[1];
        }

        $files = [];
        $pageToken = null;

        do {
            $params = [
                'q'        => $q,
                'fields'   => "nextPageToken, files({$fields})",
                'orderBy'  => $orderBy,
                'pageSize' => 1000, // optional: can be adjusted (max 1000)
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $response = $service->files->listFiles($params);

            // Uncomment for debugging if needed
            // Core::dump($response);

            foreach ($response->getFiles() as $file) {
                // Skip folders or shortcuts based on flags
                if (isset($file->mimeType)) {
                    if ($file->mimeType === 'application/vnd.google-apps.folder' && !$includeFolders) {
                        continue;
                    }
                    if ($file->mimeType === 'application/vnd.google-apps.shortcut' && !$includeShortcuts) {
                        continue;
                    }
                }

                $values = [];
                foreach ($keys as $key) {
                    $values[$key] = $file->$key ?? null;
                }
                $files[] = $values;
            }

            // Get the next page token (if any)
            $pageToken = $response->getNextPageToken();
        } while ($pageToken !== null);

        return $files;
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
        string  $folderId,
        string  $permission = 'reader',
        string  $postName = 'upload',
        string  $fileName = '',
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
            'fields' => 'id',
            'supportsAllDrives' => true,
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

        $service->permissions->create($file->id, $userPermission, [
            'fields' => 'id',
            'supportsAllDrives' => true,
        ]);

        $file = $service->files->get($file->id, [
            'fields' => 'webViewLink',
            'supportsAllDrives' => true,
        ]);
        return $file->webViewLink;
    }
}