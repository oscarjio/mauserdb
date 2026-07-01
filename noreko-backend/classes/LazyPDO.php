<?php

/**
 * LazyPDO — skjuter upp den faktiska DB-anslutningen tills en query faktiskt körs.
 *
 * Rotproblem: api.php gjorde `new PDO()` EAGER i bootstrap på VARJE request över
 * den strypta DB-tunneln — även för endpoints som svarar från filcache
 * (feature-flags, news) eller proxas till Pi:n (statistics, oee-trend,
 * lineskiftrapport). De långsamma tunnel-anslutningarna höll PHP-FPM-workers →
 * poolen tog slut → 503-burst vid boot → appen frös på "Laddar Mauserdb".
 *
 * LazyPDO öppnar main-DB-anslutningen FÖRST när en controller faktiskt anropar
 * query/prepare/exec/... . Cachade och Pi-proxade grenar returnerar innan de rör
 * $pdo → ingen main-DB-conn öppnas alls för dem = drastiskt färre samtidiga
 * tunnel-conns/workers vid boot-burst.
 *
 * Extendar PDO så att ALLA `PDO`-typhints i controllers fortsätter gälla
 * (instanceof PDO === true). parent::__construct() anropas först i connect().
 */
class LazyPDO extends PDO
{
    /** @var string */
    private $lazyDsn;
    /** @var string|null */
    private $lazyUser;
    /** @var string|null */
    private $lazyPass;
    /** @var array */
    private $lazyOptions;
    /** @var bool */
    private $lazyConnected = false;

    public function __construct($dsn, $user = null, $pass = null, $options = null)
    {
        $this->lazyDsn     = $dsn;
        $this->lazyUser    = $user;
        $this->lazyPass    = $pass;
        $this->lazyOptions = $options ?? [];
        // Medvetet INGET parent::__construct() här — ansluts lazy vid connect().
    }

    /** Öppnar den riktiga anslutningen vid första behov (idempotent). */
    private function lazyConnect(): void
    {
        if ($this->lazyConnected) {
            return;
        }
        parent::__construct($this->lazyDsn, $this->lazyUser, $this->lazyPass, $this->lazyOptions);
        $this->lazyConnected = true;
        // Timezone-synk måste ske vid första riktiga connect (samma som api.php gjorde).
        try { parent::exec("SET time_zone = 'Europe/Stockholm'"); } catch (\Throwable $e) { /* timezone-tabeller ej installerade — ignoreras */ }
    }

    #[\ReturnTypeWillChange]
    public function prepare($query, $options = [])
    {
        $this->lazyConnect();
        return parent::prepare($query, $options);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $fetchMode = null, ...$fetchModeArgs)
    {
        $this->lazyConnect();
        if ($fetchMode === null) {
            return parent::query($query);
        }
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    #[\ReturnTypeWillChange]
    public function exec($statement)
    {
        $this->lazyConnect();
        return parent::exec($statement);
    }

    #[\ReturnTypeWillChange]
    public function beginTransaction()
    {
        $this->lazyConnect();
        return parent::beginTransaction();
    }

    #[\ReturnTypeWillChange]
    public function commit()
    {
        $this->lazyConnect();
        return parent::commit();
    }

    #[\ReturnTypeWillChange]
    public function rollBack()
    {
        $this->lazyConnect();
        return parent::rollBack();
    }

    #[\ReturnTypeWillChange]
    public function inTransaction()
    {
        $this->lazyConnect();
        return parent::inTransaction();
    }

    #[\ReturnTypeWillChange]
    public function lastInsertId($name = null)
    {
        $this->lazyConnect();
        return parent::lastInsertId($name);
    }

    #[\ReturnTypeWillChange]
    public function quote($string, $type = PDO::PARAM_STR)
    {
        $this->lazyConnect();
        return parent::quote($string, $type);
    }

    #[\ReturnTypeWillChange]
    public function setAttribute($attribute, $value)
    {
        $this->lazyConnect();
        return parent::setAttribute($attribute, $value);
    }

    #[\ReturnTypeWillChange]
    public function getAttribute($attribute)
    {
        $this->lazyConnect();
        return parent::getAttribute($attribute);
    }

    #[\ReturnTypeWillChange]
    public function errorInfo()
    {
        $this->lazyConnect();
        return parent::errorInfo();
    }

    #[\ReturnTypeWillChange]
    public function errorCode()
    {
        $this->lazyConnect();
        return parent::errorCode();
    }
}
