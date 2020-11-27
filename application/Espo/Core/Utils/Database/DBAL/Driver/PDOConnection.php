<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Espo\Core\Utils\Database\DBAL\Driver;

use Doctrine\DBAL\Driver\Connection;

use PDO;

/**
 * For PHP 8.0 support. To be removed.
 */
class PDOConnection implements Connection
{
    protected $pdo;

    public function __construct($dsn, $user = null, $password = null, array $options = null)
    {
        $this->pdo = new PDO($dsn, $user, $password, $options);

        $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class, [$this->pdo]]);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    function setAttribute($attribute, $value)
    {
        return $this->pdo->setAttribute($attribute, $value);
    }

    function prepare($prepareString)
    {
        return $this->pdo->prepare($prepareString);
    }

    function query()
    {
        $statement = func_get_args()[0] ?? null;

        return $this->pdo->query($statement);
    }

    function quote($input, $type = PDO::PARAM_STR)
    {
        return $this->pdo->quote($input, $type);
    }

    function exec($statement)
    {
        return $this->pdo->exec($statement);
    }

    function lastInsertId($name = null)
    {
        return $this->pdo->lastInsertId($name);
    }

    function inTransaction()
    {
        return $this->pdo->inTransaction();
    }

    function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    function commit()
    {
        return $this->pdo->commit();
    }

    function rollBack()
    {
        return $this->pdo->rollBack();
    }

    function errorCode()
    {
        return $this->pdo->errorCode();
    }

    function errorInfo()
    {
        return $this->pdo->errorInfo();
    }

    public static function getAvailableDrivers()
    {
        return PDO::getAvailableDrivers();
    }
}
