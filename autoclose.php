<?php

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Yaml\Yaml;

require_once 'vendor/autoload.php';

$parameters_file = __DIR__ . '/config/parameters.yml';

$output          = new ConsoleOutput();
$outputFormatter = new OutputFormatter();
$output->setFormatter($outputFormatter);

if (!file_exists($parameters_file)) {
    $output->writeln('<error>You need to create a parameters file.</error>');
    die();
}

$parameters = Yaml::parse(file_get_contents($parameters_file));

$date_limit  = new \DateTime($parameters['date_limit']);
$project_ids = $parameters['project_ids'];

$output->writeln(sprintf('<info>Fetching issues not updated since %s.</info>', $date_limit->format('Y-m-d')));

$client = new Redmine\Client($parameters['redmine']['url'], $parameters['redmine']['api_key']);
foreach ($project_ids as $project_id) {
    $project_res = $client->get('/projects/' . urlencode($project_id) . '.json');
    $project     = $project_res['project'];
    $output->writeln(sprintf('Processing project "%s" (%s).', $project_id, $project['name']));

    $issues_res = $client->issue->all([
        'status_id'  => $parameters['redmine']['resolved_id'],
        'updated_on' => '<=' . $date_limit->format('Y-m-d'),
        'project_id' => $project_id,
    ]);

    foreach ($issues_res['issues'] as $issue) {
        $output->writeln(sprintf('Closing issue %d: %s.', $issue['id'], $issue['subject']));
        $client->issue->update($issue['id'], [
            'status_id' => $parameters['redmine']['closed_id'],
            'notes'     => $parameters['close_message'],
        ]);
    }
}
