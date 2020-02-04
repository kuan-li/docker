<?php

namespace Spatie\Docker;

use Spatie\Macroable\Macroable;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DockerContainerInstance
{
    use Macroable;

    private DockerContainer $config;

    private string $dockerIdentifier;

    private string $name;

    public function __construct(
        DockerContainer $config,
        string $dockerIdentifier,
        string $name
    ) {
        $this->config = $config;

        $this->dockerIdentifier = $dockerIdentifier;

        $this->name = $name;
    }

    public function __destruct()
    {
        if ($this->config->stopAfterCompletion) {
            $this->stop();
        }
    }

    public function stop()
    {
        $fullCommand = "docker stop {$this->getShortDockerIdentifier()}";

        $process = Process::fromShellCommandline($fullCommand);

        $process->run();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(): DockerContainer
    {
        return $this->config;
    }

    public function getDockerIdentifier(): string
    {
        return $this->dockerIdentifier;
    }

    public function getShortDockerIdentifier(): string
    {
        return substr($this->dockerIdentifier, 0, 12);
    }

    public function run(string $command): Process {

        $fullCommand = "echo \"{$command}\" | docker exec --interactive {$this->getShortDockerIdentifier()} bash -";

        $process = Process::fromShellCommandline($fullCommand);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    public function addPublicKey(string $pathToPublicKey): self
    {
        $authorizedKeysPath = "/root/.ssh/authorized_keys";

        $publicKeyContents = trim(file_get_contents($pathToPublicKey));

        $this->run('echo \'' . $publicKeyContents .'\' >> ' . $authorizedKeysPath);

        $this->run("chmod 600 {$authorizedKeysPath}");
        $this->run("chown root:root {$authorizedKeysPath}");

        return $this;
    }

    public function addFiles(string $sourceOnHost, string $destinationInContainer): self
    {
        $process = Process::fromShellCommandline("docker cp {$sourceOnHost} {$this->getShortDockerIdentifier()}:{$destinationInContainer}");
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $this;
    }
}
