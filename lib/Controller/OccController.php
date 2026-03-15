<?php

namespace OCA\OCCWeb\Controller;

use Exception;
use OC;
use OC\Console\Application;
use OC\MemoryInfo;
use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class OccController extends Controller
{
  private $logger;
  private $userId;

  private $application;
  private $output;

  public function __construct($AppName, IRequest $request, $userId)
  {
    parent::__construct($AppName, $request);
    $this->logger = OC::$server->get(LoggerInterface::class);
    $this->userId = $userId;

    $this->application = new Application(
      OC::$server->get(\OCP\ServerVersion::class),
      OC::$server->getConfig(),
      OC::$server->get(\OCP\EventDispatcher\IEventDispatcher::class),
      new FakeRequest(),
      $this->logger,
      OC::$server->query(MemoryInfo::class),
      OC::$server->get(\OCP\App\IAppManager::class), // Obtain the IAppManager
      OC::$server->get(\OCP\Defaults::class)
    );
    $this->application->setAutoExit(false);
    $this->output = new OccOutput(OutputInterface::VERBOSITY_NORMAL, true);
    $this->application->loadCommands(new StringInput(""), $this->output);
  }

  /**
   * @NoCSRFRequired
   * @AdminRequired
   */
  public function index()
  {
    return new TemplateResponse('occweb', 'index');
  }

  /**
   * @param $input
   * @return string
   */
  private function run($input)
  {
    try {
      $this->application->run($input, $this->output);
      return $this->output->fetch();
    } catch (Exception $ex) {
      $this->logger->error($ex->getMessage(), ['exception' => $ex]);
      return "error: " . $ex->getMessage();
    }
  }

  /**
   * @param string $command
   * @return DataResponse
   * @AdminRequired
   */
  public function cmd($command)
  {
    $startedAt = microtime(true);
    $rawCommand = trim((string)$command);
    $commandName = strtok($rawCommand, ' ') ?: 'unknown';

    $this->logger->info('occweb command started', [
      'user' => $this->userId,
      'command' => $commandName,
    ]);

    $input = new StringInput($rawCommand);
    $response = $this->run($input);

    $this->logger->info('occweb command finished', [
      'user' => $this->userId,
      'command' => $commandName,
      'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
    ]);

    return new DataResponse($response);
  }

  /**
   * @AdminRequired
   */
  public function list() {
    $output = $this->run(new StringInput('list --raw'));
    $lines = preg_split('/\r\n|\r|\n/', (string)$output);
    $cmds = [];

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }

      $parts = preg_split('/\s+/', $line, 2);
      if (!empty($parts[0])) {
        $cmds[] = $parts[0];
      }
    }

    return new DataResponse(array_values(array_unique($cmds)));
  }
}
