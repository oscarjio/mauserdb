<?php

declare(strict_types=1);

class WebhookProcessor {

    public PDO $db;
    public $line;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function process(string $type, array $data): void {


        // Kontrollera vilken linje som anropet kommer från
        switch ($_GET["line"]) {
            case 'tvattlinje':
                $this->line = "tvattlinje";
                $tvattlinje = new TvattLinje($this);
                // Kontrollera vilken typ av anrop som körs.
                switch ($type) {
                    case 'cycle':
                        $tvattlinje->handleCycle($data);
                        break;
                    case 'running':
                        $tvattlinje->handleRunning($data);
                        break;
                    case 'rast':
                        $tvattlinje->handleRast($data);
                        break;
                    default:
                        throw new InvalidArgumentException('Unsupported webhook type: ' . $type);
                }
                break;
                case 'rebotling':
                    $this->line = "rebotling";
                    $rebotling = new Rebotling($this);
                    // Kontrollera vilken typ av anrop som körs. 
                    switch ($type) {
                        case 'cycle':
                            $rebotling->handleCycle($data);
                            break;
                        case 'running':
                            $rebotling->handleRunning($data);
                            break;
                        case 'rast':
                            $rebotling->handleRast($data);
                            break;
                        case 'skiftrapport':
                            $rebotling->handleSkiftrapport($data);
                            break;
                        default:
                            throw new InvalidArgumentException('Unsupported webhook type: ' . $type);
                    }
                break;
            default:
                throw new InvalidArgumentException('Unsupported webhook line: ' . $_GET["line"]);
        }
    }
}
