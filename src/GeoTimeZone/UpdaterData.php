<?php

namespace GeoTimeZone;

use ZipArchive;
use ErrorException;
use GuzzleHttp\Client;
use GeoTimeZone\Quadrant\Indexer;


class UpdaterData
{
    const DOWNLOAD_DIR = "downloads/";
    const TIMEZONE_FILE_NAME = "timezones";
    const REPO_HOST = "https://api.github.com";
    const REPO_USER = "node-geo-tz";
    const REPO_PATH = "/repos/evansiroky/timezone-boundary-builder/releases/latest";
    const GEO_JSON_DEFAULT_URL = "none";
    const GEO_JSON_DEFAULT_NAME = "geojson";
    
    protected $mainDir = null;
    protected $downloadDir = null;
    protected $previousUpdate = null;
    protected $downloadFile = null;
    protected $timezonesSourcePath = null;
    
    /**
     * UpdaterData constructor.
     * @param $dataDirectory
     * @throws ErrorException
     */
    public function __construct($dataDirectory = null, $filename = self::GEO_JSON_DEFAULT_NAME, $previousUpdate = null)
    {
        if ($dataDirectory == null) {
            throw new ErrorException("ERROR: Invalid data directory.");
        }else{
            $this->mainDir = $dataDirectory;
            $this->downloadDir = $dataDirectory . DIRECTORY_SEPARATOR . self::DOWNLOAD_DIR;
            $this->downloadFile = $filename;
            $this->previousUpdate = $previousUpdate;
        }
    }
    
    /**
     * Get complete json response from repo
     * @param $url
     * @return mixed
     */
    protected function getResponse($url)
    {
        $client = new Client();
        $response = $client->request('GET', $url);
        return $response->getBody()->getContents();
    }
    
    /**
     * Download zip file
     * @param $url
     * @param string $destinationPath
     */
    protected function getZipResponse($url, $destinationPath = "none")
    {
        exec("wget {$url} --output-document={$destinationPath}");
    }
    
    /**
     * Get timezones json url
     * @param $data
     * @return string
     */
    protected function getGeoJsonUrl($data)
    {
        $jsonResp = json_decode($data, true);
        $geoJsonUrl = self::GEO_JSON_DEFAULT_URL;
        foreach ($jsonResp['assets'] as $asset) {
            if (strpos($asset['name'], $this->downloadFile) !== false || $asset['name'] == $this->downloadFile) {
                $geoJsonUrl = $asset['browser_download_url'];
                break;
            }
        }
        return $geoJsonUrl;
    }

    /**
     * Get timezones json url
     * @param $data
     * @return array
     */
    protected function getGeoJsonUrlAndDate($data)
    {
        $jsonResp = json_decode($data, true);
        $info = [
            'url' => self::GEO_JSON_DEFAULT_URL,
            'created_at' => date('Y-m-d H:i:s')
        ];
        foreach ($jsonResp['assets'] as $asset) {
            if (strpos($asset['name'], $this->downloadFile) !== false || $asset['name'] == $this->downloadFile) {
                $info['url'] = $asset['browser_download_url'];
                $info['created_at'] = $asset['created_at'];
                break;
            }
        }
        return $info;
    }
    
    /**
     * Get url and created date
     */
    protected function getInfo()
    {
        $response = $this->getResponse(self::REPO_HOST . self::REPO_PATH);
        return $this->getGeoJsonUrlAndDate($response);
    }

    /**
     * Download last version reference repo
     */
    protected function downloadLastVersion($geoJsonUrl)
    {
        if ($geoJsonUrl != self::GEO_JSON_DEFAULT_URL) {
            if (!is_dir($this->mainDir)) {
                mkdir($this->mainDir);
            }
            if (!is_dir($this->downloadDir)) {
                mkdir($this->downloadDir);
            }
            $this->getZipResponse($geoJsonUrl, $this->downloadDir . self::TIMEZONE_FILE_NAME . ".zip");
        }
    }
    
    /**
     * Unzip data
     * @param $filePath
     * @return bool
     */
    protected function unzipData($filePath)
    {
        $zip = new ZipArchive();
        $controlFlag = false;
        if ($zip->open($filePath) === TRUE) {
            $zipName = basename($filePath, ".zip");
            if (!is_dir($this->downloadDir . $zipName)) {
                mkdir($this->downloadDir . $zipName);
            }
            echo $this->downloadDir . $zipName . "\n";
            $zip->extractTo($this->downloadDir . $zipName);
            $zip->close();
            $controlFlag = true;
            unlink($filePath);
        }
        return $controlFlag;
    }
    
    /**
     * Rename downloaded timezones json file
     * @return bool
     */
    protected function renameTimezoneJson()
    {
        $path = realpath($this->downloadDir . self::TIMEZONE_FILE_NAME . DIRECTORY_SEPARATOR);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        $jsonPath = "";
        foreach ($files as $pathFile => $file) {
            if (strpos($pathFile, ".json")) {
                $jsonPath = $pathFile;
                break;
            }
        }
        $this->timezonesSourcePath = dirname($jsonPath) . DIRECTORY_SEPARATOR . self::TIMEZONE_FILE_NAME . ".json";
        echo $this->timezonesSourcePath . "\n";
        return rename($jsonPath, $this->timezonesSourcePath);
    }
    
    /**
     * Remove all directories tree in a particular data folder
     * @param $path
     * @param $validDir
     */
    protected function removeData($path, $validDir = null)
    {
        $removeAll = !$validDir ? true : false;
        
        if (is_dir($path)) {
            $objects = scandir($path);
            foreach ($objects as $object) {
                $objectPath = $path . DIRECTORY_SEPARATOR . $object;
                if ($object != "." && $object != "..") {
                    if (is_dir($objectPath)) {
                        if (in_array(basename($object), $validDir) || $removeAll) {
                            $this->removeData($objectPath, $validDir);
                        }
                    } else {
                        unlink($objectPath);
                    }
                }
            }
            if (in_array(basename($path), $validDir) || $removeAll) {
                rmdir($path);
            }
        }
        return;
    }
    
    /**
     * Remove data tree
     */
    protected function removeDataTree()
    {
        $validDir = [
            Indexer::LEVEL_A,
            Indexer::LEVEL_B,
            Indexer::LEVEL_C,
            Indexer::LEVEL_D
        ];
        $this->removeData($this->mainDir . DIRECTORY_SEPARATOR, $validDir);
    }
    
    
    /**
     * Remove downloaded data
     */
    protected function removeDownloadedData()
    {
        $validDir = array("downloads", "timezones", "dist");
        $this->removeData($this->downloadDir, $validDir);
    }
    
    /**
     * Add folder to zip file
     * @param $mainDir
     * @param $zip
     * @param $exclusiveLength
     */
    protected function folderToZip($mainDir, &$zip, $exclusiveLength)
    {
        $handle = opendir($mainDir);
        while (false !== $f = readdir($handle)) {
            if ($f != '.' && $f != '..') {
                $filePath = $mainDir . DIRECTORY_SEPARATOR . $f;
                $localPath = substr($filePath, $exclusiveLength);
                if (is_file($filePath)) {
                    $zip->addFile($filePath, $localPath);
                } elseif (is_dir($filePath)) {
                    $zip->addEmptyDir($localPath);
                    $this->folderToZip($filePath, $zip, $exclusiveLength);
                }
            }
        }
        closedir($handle);
    }
    
    /**
     * Compress directory
     * @param $sourcePath
     * @param $outZipPath
     */
    protected function zipDir($sourcePath, $outZipPath)
    {
        $pathInfo = pathInfo($sourcePath);
        $parentPath = $pathInfo['dirname'];
        $dirName = $pathInfo['basename'];
        
        $z = new ZipArchive();
        $z->open($outZipPath, ZIPARCHIVE::CREATE);
        $z->addEmptyDir($dirName);
        $this->folderToZip($sourcePath, $z, strlen($parentPath . DIRECTORY_SEPARATOR));
        $z->close();
    }
    
    /**
     * Main function that runs all updating process
     */
    public function updateData()
    {
        echo "Checking updates...\n";
        $info = $this->getInfo();
        if(!empty($this->previousUpdate) && strtotime($info['created_at']) <= strtotime($this->previousUpdate)) {
            echo "Last version already downloaded.\n";
            return false;
        }
        echo "Downloading data...\n";
        $this->downloadLastVersion($info['url']);
        echo "Unzip data...\n";
        $this->unzipData($this->downloadDir . self::TIMEZONE_FILE_NAME . ".zip");
        echo "Rename timezones json...\n";
        $this->renameTimezoneJson();
        echo "Remove previous data...\n";
        $this->removeDataTree();
        echo "Creating quadrant tree data...\n";
        $geoIndexer = new Indexer($this->mainDir, $this->timezonesSourcePath);
        $geoIndexer->createQuadrantTreeData();
        echo "Remove downloaded data...\n";
        $this->removeDownloadedData();
        echo "Zipping quadrant tree data...";
        $this->zipDir($this->mainDir, $this->mainDir . ".zip");
        return true;
    }
}

