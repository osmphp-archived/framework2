<?php

namespace Manadev\Framework\Layers\Traits;

use Manadev\Core\App;
use Manadev\Framework\Logging\Logs;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

trait PropertiesTrait
{
    public function Manadev_Framework_Logging_Logs__layers(Logs $logs) {
        global $m_app; /* @var App $m_app */

        // create new logging channel
        $logger = new Logger('layers');

        if (!$m_app->settings->log_layers) {
            return $logger->pushHandler(new NullHandler());
        }

        // write each included layer file to temp/ENV/log/layers/UNIQUE_FILENAME.log
        $logger->pushHandler($handler = new StreamHandler($m_app->path(
            "{$m_app->temp_path}/log/layers/{$logs->unique_filename}")));

        // write file name and file contents of each reported layer file
        $handler->setFormatter(new LineFormatter("# %context.filename% \n\n%extra.contents%\n\n",
            LineFormatter::SIMPLE_FORMAT, true));

        // as user code only provides file name, add file contents to log record using processor
        $logger->pushProcessor(function($record) use ($m_app) {
            $record['extra']['contents'] = file_get_contents($record['context']['filename']);

            return $record;
        });

        return $logger;
    }

}