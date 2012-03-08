<?php
class CodeCoverageToolbarExtension
{

    static $options;
    protected $started = false;

    public function startSection($section_id)
    {
        if (!$this->started)
        {
            $this->started = true;
            self::$options = PhpDebugToolbar::$options['code_coverage'];

            if (isset($_GET['PhpDebugToolbarCodeCoverage_Action']))
            {
                header('Content-Type: text/plain');
                header('Cache-Control: private, no-cache, max-age=0');
                $action = $_GET['PhpDebugToolbarCodeCoverage_Action'];
                if (!isset($_GET['PhpDebugToolbarCodeCoverage_Password']))
                {
                    $_GET['PhpDebugToolbarCodeCoverage_Password'] = '';
                }
                $password = $_GET['PhpDebugToolbarCodeCoverage_Password'];

                if ($password != self::$options['password'])
                {
                    header('401 Unauthorized', true, 401);
                    die();
                }

                if ($action === 'start')
                {
                    file_put_contents($this->getFilename(), '');
                    echo "true";
                }
                elseif ($action === 'stop')
                {
                    unlink($this->getFilename());
                    echo "true";
                }
                elseif ($action === 'report_json')
                {
                    echo '{"base_path":' . json_encode($this->getBasePath()) . ', "files":[' . str_replace(PHP_EOL, ",", file_get_contents($this->getFilename())) . ']}';
                }
                elseif ($action === 'report')
                {
                    $this->printSummaryAndDie();
                }
                else
                {
                    echo "false";
                }
                die();
            }

            if (file_exists($this->getFilename()))
            {
                xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

                register_shutdown_function('CodeCoverageToolbarExtension::onShutdown');
            }
        }
    }

    protected function getBasePath()
    {
        $base_path = null;

        foreach (self::$options['include'] as $include)
        {
            if ($base_path === null)
            {
                $base_path = $include;
            }
            else
            {
                $max_length = min(strlen($base_path), strlen($include));
                for ($i = 0; $i < $max_length; $i++)
                {
                    if ($base_path[$i] != $include[$i])
                    {
                        $base_path = substr($base_path, 0, $i);
                        break;
                    }
                }
            }
        }

        return $base_path;
    }

    protected function getFilename()
    {
        return self::$options['filename'];
    }

    static function isFilenameIncluded($filename)
    {
        foreach (self::$options['include'] as $include)
        {
            if (strpos($filename, $include) === 0)
            {
                foreach (self::$options['exclude'] as $exclude)
                {
                    if (strpos($filename, $exclude) === 0)
                    {
                        return false;
                    }
                }
                return true;
            }
        }
        return false;
    }

    static function onShutdown()
    {
        $included_files_data = array();
        foreach (xdebug_get_code_coverage() as $file => $data)
        {
            if (self::isFilenameIncluded($file))
            {
                $included_files_data[$file] = $data;
            }
        }
        file_put_contents(self::$options['filename'], json_encode($included_files_data) . PHP_EOL, FILE_APPEND);
    }

    public function finishSection($section_id)
    {
    }

    protected function printSummaryAndDie()
    {
        $base_path = $this->getBasePath();

        $full_report = array();

        foreach (explode(PHP_EOL, file_get_contents($this->getFilename())) as $raw_line)
        {
            if (empty($raw_line))
            {
                continue;
            }

            $line = json_decode($raw_line, true);
            foreach ($line as $long_coverage_file => $coverage_data)
            {
                $coverage_file = substr($long_coverage_file, strlen($base_path));

                if (!isset($full_report[$coverage_file]))
                {
                    $full_report[$coverage_file] = array();
                }
                foreach ($coverage_data as $line => $count)
                {
                    if (isset($full_report[$coverage_file][$line]))
                    {
                        $full_report[$coverage_file][$line] = max($full_report[$coverage_file][$line], $count);
                    }
                    else
                    {
                        $full_report[$coverage_file][$line] = $count;
                    }
                }
            }
        }

        ksort($full_report);

        $overall_total_statements = 0;
        $overall_covered_statements = 0;

        echo " Executed lines of code " . PHP_EOL;
        echo "========================" . PHP_EOL;
        echo "" . PHP_EOL;
        foreach ($full_report as $coverage_file => $coverage_data)
        {
            $covered_statements = 0;
            $total_statements = 0;
            foreach ($coverage_data as $line => $count)
            {
                if ($count > -2)
                {
                    if ($count > 0)
                    {
                        $covered_statements++;
                    }

                    $total_statements++;
                }
            }
            $overall_covered_statements += $covered_statements;
            $overall_total_statements += $total_statements;
            echo "   - " . str_pad(floor($covered_statements * 100 / $total_statements), 3, ' ', STR_PAD_LEFT) . "% " . $coverage_file . PHP_EOL;
        }
        echo "" . PHP_EOL;
        
        if ($overall_total_statements === 0)
        {
            echo "" . PHP_EOL;
            echo "No code executed, yet. Please use the application page, before you view this report." . PHP_EOL;
            echo "" . PHP_EOL;
            die();
        }

        $overall_code_coverage = 100 * $overall_covered_statements / $overall_total_statements;
        echo "Executed $overall_code_coverage% ($overall_covered_statements/$overall_total_statements loc) of all statements!" . PHP_EOL;
        echo "" . PHP_EOL;
        
        echo " Unexecuted lines of code " . PHP_EOL;
        echo "==========================" . PHP_EOL;
        echo "" . PHP_EOL;
        $coverage_file_content_cache = array();

        foreach ($full_report as $coverage_file => $coverage_data)
        {
            $covered_statements = 0;
            $total_statements = 0;
            $max_line = max(array_keys($coverage_data));
            foreach ($coverage_data as $line => $count)
            {
                if ($count == -1)
                {
                    if (empty($coverage_file_content_cache[$coverage_file]))
                    {
                        $coverage_file_content_cache[$coverage_file] = explode("\n", file_get_contents($base_path . $coverage_file));
                    }
                    echo basename($coverage_file) . ":" . str_pad($line, strlen($max_line)) . " > " . $coverage_file_content_cache[$coverage_file][$line - 1] . PHP_EOL;
                }
            }
        }

        die();
    }

}
