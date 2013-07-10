<?php

class SchedulerCommand extends CConsoleCommand
{

    const ONCE = 'once';
    const HOURLY = 'hourly';
    const DAILY = 'daily';
    const WEEKLY = 'weekly';
    const MONTHLY = 'monthly';

    /**
     * Connection to yii database
     */
    private $connection;

    /**
     * Basic class setup
     */
    public function init() {
        $this->connection = Yii::app()->db;
    }

    /**
     * Checks if the yii-schedules table exists
     * 
     * @return boolean - true if the table exists, false otherwise
     */
    public function tableExists() {
        $sql = "SELECT 1 FROM yii-schedules LIMIT 1";
        $result = $this->connection->createCommand()
            ->select('*')
            ->from('yii-schedules')
            ->limit(1);
        try {
            $result->queryScalar();
        } catch (Exception $e) {
            return false;
        }
        return true;
    }

    /**
     * Creates the yii-schedules table
     */
    public function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `yii-schedules` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(100) NOT NULL,
            `frequency` varchar(100) NOT NULL DEFAULT '',
            `scheduled` datetime NOT NULL,
            `executed` tinyint(1) NOT NULL DEFAULT '0',
            `deleted` tinyint(1) NOT NULL DEFAULT '0',
            `url` varchar(500) NULL DEFAULT NULL,
            `command` varchar(500) NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8";
        $this->connection->createCommand($sql)->execute();
    }

    /**
     * Adds a new scheduled item
     * 
     * @param  string  $name    name to group this type of scheduled item with (anything you like)
     * @param  string  $time    when to schedule the scheduled item for (Y-m-d or Y-m-d_H:i:s)
     * @param  string  $url     url to cURL when a scheduled item is triggered
     * @param  string  $command Can be used instead of a url. Will be run with exec
     * @param  boolean $once    (default) if true or none of the following params are true, 
     *                          the scheduled item will only be executed 1 time
     * @param  boolean $daily   if --daily is passed as a command line arg, the scheduled item will
     *                          repeat daily at the same time
     * @param  boolean $weekly  if --weekly is passed as a command line arg, the scheduled item will
     *                          repeat weekly at the same time
     * @param  boolean $monthly if --monthly is passed as a command line arg, the scheduled item will
     *                          repeat monthly at the same time
     */
    public function actionAdd($name, $time, $url = null, $command = null,
        $once = false, $hourly = false, $daily = false, $weekly = false, $monthly = false
    ) {

        //create table if needed
        if (false === $this->tableExists())
            $this->createTable();

        if ($once === false && $hourly === false && $daily === false && $weekly === false && $monthly === false)
            $once = true;

        if ((is_null($url) && is_null($command)) || (($url && $command)))
            die("you must provide either a --command or a --url parameter (but not both) eg. --url=http.. or --command='php myscript.php'\n");

        $time = str_replace('_', ' ', $time);

        try {
            $datetime = new DateTime($time);
            // check that $time is today or later
            if (false === ($datetime >= new DateTime))
                throw new Exception('');
        } catch (Exception $e) {
            echo "\ninvalid time value, check that the time " . 
                "you specified is not in the past and is in " . 
                "the form Y-m-d_H:i:s " . 
                "eg. 2020-01-01 or 2020-01-01_14:00:00 will both work...\n\n";
            Yii::app()->end();
        }

        if ($once)
            $this->schedule($name, $datetime, $url, $command, self::ONCE);
        else if ($hourly)
            $this->schedule($name, $datetime, $url, $command, self::HOURLY); 
        else if ($daily)
            $this->schedule($name, $datetime, $url, $command, self::DAILY); 
        else if ($weekly)
            $this->schedule($name, $datetime, $url, $command, self::WEEKLY);
        else if ($monthly)
            $this->schedule($name, $datetime, $url, $command, self::MONTHLY);
        die;
        
    }

    /**
     * Helper method to perform actual scheduling
     * 
     * @param  string   $name      name to group scheduled items by
     * @param  DateTime $time      php DateTime object for when the scheduled item should happen
     * @param  string   $url       url to cURL when scheduled item is triggered
     * @param  string   $command   command to be shell_exec'd
     * @param  string   $frequency how often to repeat scheduled item
     */
    private function schedule($name, DateTime $time, $url, $command, $frequency) {
        
        $readableTime = $time->format('Y-m-d H:i:s');
        echo "\n(re)Scheduling '{$frequency}' scheduled item for {$readableTime}...\n\n";

        $this->connection->createCommand()->insert('yii-schedules', array(
            'name' => $name,
            'frequency' => $frequency,
            'scheduled' => $time->format('Y-m-d H:i:s'),
            'url' => $url,
            'command' => $command,
            'deleted' => 0,
            'executed' => 0
        ));

    }

    /**
     * Wrapper for schedule method, increments time depending on 
     * how when the scheduled item should repeat before calling schedule
     * method
     *
     * @param  string   $name      name to group scheduled items by
     * @param  DateTime $time      php DateTime object for when the scheduled item should happen
     * @param  string   $url       url to cURL when scheduled item is triggered
     * @param  string   $command   command to be shell_exec'd
     * @param  string   $frequency how often to repeat scheduled item
     */
    private function reSchedule($name, DateTime $time, $url, $command, $frequency) {

        if ($frequency === 'once') 
            return;

        switch ($frequency) {

            case self::HOURLY:
                $interval = 'PT1H';
                break;

            case self::DAILY:
                $interval = 'P1D';
                break;

            case self::WEEKLY:
                $interval = 'P7D';
                break;

            case self::MONTHLY:
                $interval = 'P1M';
                break;
            default:
                echo 'invalid frequency...';
                break;
        }

        $time->add(new DateInterval($interval));

        $this->schedule($name, $time, $url, $command, $frequency);


    }

    /**
     * Removes all scheduled scheduled items. Soft deletes from the database
     * by setting the deleted column to 1
     */
    public function actionRemoveall() {
        echo "\nremoving all scheduled items...\n\n";
        $this->connection->createCommand()->update('yii-schedules', array(
            'deleted' => 1
        ));
        Yii::app()->end();
    }

    /**
     * Helper method to cURL a given url and return the result
     * 
     * @param  string $url the url to cURL
     * 
     * @return string      the result of the cURL operation
     */
    public function executeUrl($url) {
        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, TRUE); 
        curl_setopt($ch, CURLOPT_NOBODY, FALSE); // remove body 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        $data = curl_exec($ch);
        // $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        curl_close($ch);
        return $data;
    }

    public function executeCommand($command) {
        return shell_exec($command);
    }

    /**
     * Use a cron job to hit this action as often
     * as you like. The scheduler will handle whether
     * or not to actually do anything
     */
    public function actionRun($name = 'all') {
        
        echo "\nrunning app...\n\n";

        $where = 'executed = 0 AND deleted = 0';

        $params = array();
        if ($name !== 'all') {
            $where .= ' AND name = :name';
            $params = array(':name' => $name);
        }

        //look up schedules
        $schedules = $this->connection->createCommand()
            ->select('id, name, frequency, scheduled, url, command')
            ->from('yii-schedules')
            ->where($where, $params)
            ->queryAll();

        //whether we should trigger a send
        $trigger = false;

        //execute any that are in the past
        foreach ($schedules as $schedule) {
            
            $scheduled = new DateTime($schedule['scheduled']);
            $now = new DateTime;
            
            if ($scheduled <= $now) {
                
                //mark a trigger because the scheduled item is now in the past
                //or now
                $trigger = true;
                
                //update the db, the item has now been executed
                $this->connection->createCommand()->update('yii-schedules', array(
                    'executed' => 1
                ), 'id=:id', array(':id'=>$schedule['id']));

                $url = $schedule['url'];
                if ($url)
                    $result = $this->executeUrl($url);

                $command = $schedule['command'];
                if ($command)
                    $result = $this->executeCommand($command);

                // echo "HTTP Status: {$httpCode}\n";
                echo "Command Result: {$result}\n";

                //if needed, a rescheduling should occur
                $this->reSchedule($schedule['name'], $scheduled, $schedule['url'], $schedule['command'], $schedule['frequency']);

            }

        }
        
        if ($trigger)
            echo "scheduled item successfully executed\n\n";
        else
            echo "nothing to do...\n\n";


        Yii::app()->end();
    }

    /**
     * Defines the help command which simply outputs how to use this module from the
     * command line
     */
    public function actionHelp() {
        
        $msg  = "\nadd: adds a time value to schedule scheduled items to be sent at.\n";
        $msg .= "eg. ./yiic scheduled item add --name=handle --time=2013-12-12\n";
        $msg .= "eg. ./yiic scheduled item add --name=handle --time=2013-12-12_13:00:00\n";
        $msg .= "eg. ./yiic scheduled item add --name=handle --time=2013-12-12 --once\n";
        $msg .= "eg. ./yiic scheduled item add --name=handle --time=2013-12-12 --daily\n";
        $msg .= "eg. ./yiic scheduled item add --name=handle --time=2013-12-12 --weekly\n";
        $msg .= "eg. ./yiic scheduled item add --name=handle --time=2013-12-12 --monthly\n\n";
        
        $msg .= "removeall: invalidates all currently scheduled scheduled items\n";
        $msg .= "eg. ./yiic scheduled item removeall\n\n";
        
        $msg .= "list: lists all currently set scheduled items\n";
        $msg .= "eg. ./yiic scheduled item list\n\n";

        $msg .= "run: used with a crontab. " . 
            "Have the crontab use the run command regularly " . 
            "(every hour perhaps?) Let the scheduler take care of the rest\n";
        $msg  .= "eg. cron [cron values] ./yiic scheduled item run\n\n";

        echo $msg;

    }

    /**
     * Lists currently set scheduled items
     */
    public function actionList() {
        
        $schedules = $this->connection->createCommand()
            ->select('name, url, command, frequency, scheduled')
            ->from('yii-schedules')
            ->where('executed = 0 AND deleted = 0')
            ->queryAll();

        echo "\n";
        foreach ($schedules as $schedule) {
            
            if (false === is_null($schedule['url']))
                $task = $schedule['url'];

            if (false === is_null($schedule['command']))
                $task = $schedule['command'];

            echo "{$schedule['scheduled']} - {$schedule['frequency']} - {$schedule['name']} - {$task}\n";
        }

        if (count($schedules) < 1)
            echo "no scheduled items set...\n\n";
        else
            echo "\n";

    }
    

}
