yii-scheduler
=============

Extension that schedules tasks for Yii projects

## Description
This scheduler is intended to be used to schedule various operations that should be performed either once off (once) at a certain day and time or in a repeating fashion (hourly, daily, weekly or monthly). It provides a database table where it stores scheduled items and will determine which tasks
should be run at any given moment when you run the run command. You can schedule urls to be visited via cURL or command line operations by providing the full path to the command.

## Under the hood
Basically, you should set up a cron job to run the following command regularly. Maybe every hour or less.:

```
./yiic scheduler run
```

or 

```
./yiic scheduler run --name="some name"
```

Then when cron executes the command, yii scheduler will look up the database and check if there are any entries (that are not flagged as deleted or executed) that have time values in the past. If it finds any matches, it will look up either the 'url' field and execute that via cURL or the 'command' field and run the command as is via phps shell_exec command. After the command has been run or url has been executed, if a flag other than --once has been set (ie: --hourly, --daily, --weekly, or --monthly), yii scheduler will created a new record in the table identical to the one just executed except with the time value incremented accordingly. There are problems with this approach if you stop your cron for a time and have a number of items in yii-scheduler that end up being some time in the past. If you then start up your cron again, you will get commands executing, incrementing and still being in the past and so executing again. I should fix this but currently dont have the time to do so. Be aware!

## Installation

Copy the file SchedulerCommand.php into your protected/commands directory
Add a scheduled task using the command line 'add' command. This will create the database table for you at the same time.

## Usage
1. Add scheduled tasks via the command line api (defined below) or by inserting entries in the yii-scheduler table in the database
2. Set up a cron task to regularly run the run command and let the scheduler handle the rest

## Command line API

### add
Adds a new task, creating the table yii-scheduler if it doesn't exist. You can only created scheduled tasks in the future. Trying to create tasks in the past will throw and error

#### Flags
- --name (required)
- --time (required)
- --url (optional)
- --command (optional)
- --once (optional)
- --hourly (optional)
- --daily (optional)
- --weekly (optional)
- --monthly (optional)

#### Examples:

```
./yiic scheduler add --name="some name" --time=2013-12-12 --url="http://google.com"
```

```
./yiic scheduler add --name="some name" --time=2013-12-12_13:00:00 --url="http://google.com"
```

```
./yiic scheduler add --name="some name" --time=2013-12-12 --url="http://google.com" --once
```

```
./yiic scheduler add --name="some name" --time=2013-12-12 --command="php /path/to/yiic.php myothercommand" --daily
```

```
./yiic scheduler add --name="some name" --time=2013-12-12 --url="http://google.com" --weekly
```

```
./yiic scheduler add --name="some name" --time=2013-12-12 --url="http://google.com" --monthly
```

### removeall
Soft deletes all scheduled tasks by setting the deleted column to '1' in the database

#### Examples:

```
./yiic scheduler removeall
```

### list
Lists all currently scheduled tasks

#### Examples:

```
./yiic scheduler list
```

### run
Runs all scheduled tasks that are in the past. If the --name flag is not passed, all matching tasks will be executed. If the flag is passed in, only tasks with matching 'name' field in the database will be run.

#### Flags
- --name (required)

#### Examples:

```
./yiic scheduler run
```

```
./yiic scheduler run --name="some name"
```

### help
List information and example usages of the various api commands

Examples:

```
./yiic scheduler help
```