<?php

namespace unglue\client\tasks;

use Curl\Curl;
use yii\helpers\Console;
use unglue\client\helpers\FileHelper;

class ConfigConnection
{
    public $configFile;
    public $folder;
    public $config = [];
    public $scssMap = [];
    public $jsMap = [];
    public $server;

    public function __construct($configFile, $folder, $server)
    {
        $this->configFile = $configFile;
        $this->folder = $folder;
        $this->server = $server;
        $this->config = json_decode(file_get_contents($configFile), true);
    }

    public function getHasCssConfig()
    {
        return isset($this->config['css']) ? $this->config['css'] : false;
    }

    public function getHasJsConfig()
    {
        return isset($this->config['js']) ? $this->config['js'] : false;
    }

    public function getConfigOptions()
    {
        return isset($this->config['options']) ? $this->config['options'] : [];
    }

    public function generateMap($folder, $extension, $exclude = [])
    {
        $files = FileHelper::findFiles($folder, $extension);
        $map = [];
        foreach ($files as $name => $value) {
            if (in_array($name, $exclude)) {
                //$this->infoMessage("Exclude file: " . $name);
                continue;
            }
            if (is_file($name) && is_readable($name)) {
                $map[] = ['file' => $name, 'filemtime' => filemtime($name)];
            }
        }
        unset($files);
        return $map;
    }

    public function findMapChange(array &$map)
    {
        $hasChange = false;
        foreach ($map as $key => $item) {
            $time = filemtime($item['file']);
            if ($time > $item['filemtime']) {
                $this->infoMessage("file " .$item['file'] . " has changed.");
                $hasChange = true;
                $map[$key]['filemtime'] = $time;
            }
            unset($time);
        }

        return $hasChange;
    }

    public function test()
    {
        $has = false;

        if ($this->getHasCssConfig()) {
            $this->scssMap = $this->generateMap($this->folder, 'scss');
            $has = true;
        }
        
        if ($this->getHasJsConfig()) {
            $this->jsMap = $this->generateMap($this->folder, 'js', [
                $this->createunglueFile('js'),
            ]);
            $has = true;
        }

        return $has;
    }

    public function createunglueFile($extension)
    {
        return $this->getunglueDir() . DIRECTORY_SEPARATOR . $this->getunglueFile() . '.'.$extension;
    }

    public function getunglueDir()
    {
        return dirname($this->configFile);
    }

    public function getunglueFile()
    {
        return basename($this->configFile, '.unglue');
    }

    public function iterate($force = false)
    {
        $dir = $this->getunglueDir();
        $baseName = $this->getunglueFile();

        if ($this->getHasCssConfig() && ($this->findMapChange($this->scssMap) || $force)) {
            self::infoMessage($baseName . '.css compile request');
            $css = $this->getCssResponse($this->getHasCssConfig(), $this->scssMap, $dir);
            if ($css) {
                file_put_contents($dir . DIRECTORY_SEPARATOR . $baseName . '.css', $css);
                $mapPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.css.map';
                if ($css['map']) {
                    file_put_contents($mapPath, $css['map']);
                } elseif (file_exists($mapPath)) {
                    unlink($mapPath);
                }
                self::successMessage($baseName.'.css compiled');
            }
        }

        if ($this->getHasJsConfig() && ($this->findMapChange($this->jsMap) || $force)) {
            self::infoMessage($baseName . '.js compile request');
            $js = $this->getJsResponse($this->getHasJsConfig(), $this->jsMap, $dir);
            if ($js) {
                file_put_contents($dir . DIRECTORY_SEPARATOR . $baseName . '.js', $js['code']);
                $mapPath = $dir . DIRECTORY_SEPARATOR . $baseName . '.js.map';
                if ($js['map']) {
                    file_put_contents($mapPath, $js['map']);
                } elseif (file_exists($mapPath)) {
                    unlink($mapPath);
                }
                self::successMessage($baseName.'.js compiled');
            }
        }
    }

    public function getCssResponse($config, array $maps, $dir)
    {
        $content = [
            'code' => '',
            'map' => ''
        ];
        foreach ($config as $scss) {
            $map = [];
            foreach ($maps as $file) {
                $map[] = [
                    'file' => $file['file'],
                    'code' => file_get_contents($file['file']),
                ];
            }

            $payload = [
                'distFile' => $this->getunglueFile() . '.css',
                'mainFile' => $dir . DIRECTORY_SEPARATOR . $scss,
                'files' => $map,
            ];

            $r = $this->generateRequest($this->server . '/compile/scss', $payload);

            if ($r) {
                $content['code'] .= $r['code'];
                if ($r['map']) {
                    $content['map'] .= $r['map'];
                }
            }
        }

        if (empty($content)) {
            return false;
        }
        
        return $content;
    }

    public function getJsResponse($config, array $maps, $dir)
    {
        $map = [];
        foreach ($config as $js) {
            $p = $dir . DIRECTORY_SEPARATOR . $js;
            $map[] = [
                'file' => $p,
                'code' => file_get_contents($p),
            ];
        }

        $payload = [
            'distFile' => $this->getunglueFile() . '.js',
            'files' => $map,
        ];

        $r = $this->generateRequest($this->server . '/compile/js', $payload);

        if ($r) {
            return $r;
        }

        return false;
    }

    public function generateRequest($url, array $payload)
    {
        $payload['options'] = $this->getConfigOptions();
        $json = json_encode($payload);
        $curl = new Curl();
        $curl->setHeader('Content-Type', 'application/json');
        $curl->setHeader('Content-Length', strlen($json));
        $curl->post($url, $json);
        $response = json_decode($curl->response, true);

        if ($curl->isSuccess()) {
            return $response;
        }

        $message = (isset($response['message']) && !empty($response['message'])) ? $response['message'] : $curl->error_message;

        return self::errorMessage($message);
    }

    public static function infoMessage($message)
    {
        echo "[".date("H:i:s")."] ". $message . PHP_EOL;
    }

    public static function errorMessage($message)
    {
        echo "[".date("H:i:s")."] Error: ". Console::ansiFormat($message, [Console::FG_RED]) . PHP_EOL;
        return false;
    }

    public static function successMessage($message)
    {
        echo "[".date("H:i:s")."] ". Console::ansiFormat($message, [Console::FG_GREEN]) . PHP_EOL;
        return true;
    }
}
