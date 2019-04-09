<?php

namespace App\Console\Commands\MultyProcess;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

declare(ticks = 1);

class MultyProcessCommand extends Command
{
    use SendMsgTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'MultyProcess:task 
        {--msgid= : Just Only send the messages on option id }
        {--taskid= : Just Only send the messages on option taskid }
        {--schoolid= : Just Only send the messages of this school }
        {--max= : The Max Count Limit To select data from TaskLog Everytime } 
    	{--interval-time= : The max sleep time of start to next loop } 
    	{--max-process-num= : The max process number } 
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '发送通知模板消息';

    //protected $whiteList = 
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // 处理命令行参数
        $max			= $this->option('max')				?? 1000;
        $taskId			= $this->option('taskid')			?? 0;
        $msgId			= $this->option('msgid')			?? 0;
        $schoolId		= $this->option('schoolid')			?? 0;
        $intervalTime	= $this->option('interval-time')	?? 30;
        $maxProcessNum	= $this->option('max-process-num')	?? 10;
        // 处理退出信号
        pcntl_signal(SIGTERM, function () {
            // 循环发送退出信号给子进程
            foreach ($this->childs as $pid => $dummy) {
                posix_kill($pid, SIGTERM);
            }
            // 父进程退出
            exit(0);
        });

        // 死循环
        while (true) {
            try {
                $sendLogs = $this->getSendMsg($max, $taskId, $msgId, $schoolId);
                $count    = count($sendLogs);
                #Log::debug('['.$this->msgScenes.'] sendLogs count: ' . $count);
                if (!empty($sendLogs)) {
                    // 开始时间
                    $startTime = microtime(true);
                    // 进程数 = min(最大进程数, 当前任务数)
                    $processNum = min($maxProcessNum, $count);
                    // 循环创建子进程
                    for ($i = 0; $i < $processNum; $i++) {
                        if (($pid = pcntl_fork()) == 0) {
                            // 子进程恢复默认的信号处理机制
                            pcntl_signal(SIGTERM, SIGDFL);
                            // 子进程发送模板消息
                            foreach ($sendLogs as $index => $sendLog) {
                                if ($index % $processNum == $i) {
                                    try {
                                        $result = $this->sendMsgContent((array) $sendLog);
                                        if (is_array($result)) {
                                            Log::debug('['.$this->msgScenes.'] send result: ', $result);
                                        } else {
                                            Log::debug('['.$this->msgScenes.'] send result: ' . $result);
                                        }
                                    } catch (\Exception $e) {
                                        Log::error('['.$this->msgScenes.'] send exception: ' . $e);
                                    }
                                }
                            }
                            exit(0);	// 子进程退出
                        }
                        // 父进程保存子进程进程ID
                        $this->childs[$pid] = 1;
                    }
                    // 等待子进程退出, 防止子进程变成僵尸进程
                    // 父进程必须保证这批任务全部处理完毕了, 才能执行下一轮处理, 否则可能相同的任务会被重复调用
                    while (($pid = pcntl_wait($status)) > 0) {
                        // 删除已经完成的子进程进程ID
                        #Log::debug('['.$this->msgScenes.'] exit process: #' . $pid);
                        unset($this->childs[$pid]);
                    }
                    // 执行时间
                    $executeTime = microtime(true) - $startTime;
                    #Log::debug('['.$this->msgScenes.'] executeTime: ' . round($executeTime, 3));
                }

            } catch (\Exception $e) {
                // 未知的异常
                #Log::error('['.$this->msgScenes.'] unknown exception: ' . $e);
            }

            // 休眠时间 = 间隔时间 - 执行时间, 这样如果执行已经消耗了很长时间, 就没必要再休眠这么长时间
            // 再获取下一轮模板消息了
            $sleepTime = $intervalTime - round($executeTime ?? 0);
            if ($sleepTime > 0) {
                sleep($sleepTime);
            }
        }
    }

#获取任务
	public function getSendMsg($max, $taskId, $msgId, $schoolId){

		return [];
	}

#具体工作
    public function sendMsgContent($sendMsg) {
        $this->line("开始干活 #".$sendMsg['id'] );
    }
}