<?php
/*
 * Lizzy maintains *one* SQlite DB (located in 'CACHE_PATH/.lzy_db.sqlite')
 * So, all data managed by DataStorage2 is stored in there.
 * However, shadow data files in yaml, json or cvs format may be maintained:
 *      they are imported at construction and exported at deconstruction time
 *
 * "Meta-Data" maintains info about DB-, record- and element-level locking.
 * It is maintained only with the Lizzy DB - deleting that will reset all locking.
 *
     Data-Structure:
        $structure['elements'][$elemKey] = [
          'type'
          'name'
          'formLabel'
         ]
        $structure['elemKeys'] = []
*/

namespace Usility\PageFactory\PageElements;

use Usility\PageFactory\PageFactory;
use \Kirby\Data\Yaml as Yaml;
use \Kirby\Data\Json as Json;

 // PATH_TO_APP_ROOT must to be defined by the invoking module
 // *_PATH constants must only define path starting from app-root
define('LIZZY_DB',  PFY_CACHE_PATH . '_lzy_db.sqlite');

if (!defined('LZY_LOCK_ALL_DURATION_DEFAULT')) {
    define('LZY_LOCK_ALL_DURATION_DEFAULT', 900.0); // 15 minutes
}
if (!defined('LZY_DEFAULT_DB_TIMEOUT')) {
    define('LZY_DEFAULT_DB_TIMEOUT', 0.333); // 1/3 sec
}
if (!defined('LZY_DB_POLLING_CYCLE_TIME')) {
    define('LZY_DB_POLLING_CYCLE_TIME', 50000); // 50ms [us]
}
if (!defined('LZY_DEFAULT_FILE_TYPE')) {
    define('LZY_DEFAULT_FILE_TYPE', 'json');
}


class DataStorage
{
    private $lzyDb = null;
    private $dbModeRW = null;
    private $dataFile;
    private $tableName;
    private $data = null;
    private $rawData = null;
    private $exportRequired = false;
    private $sid;
    private $format;
    private $lockDB = false;
    private $defaultTimeout = 30; // [s]
    private $defaultPollingSleepTime = LZY_DB_POLLING_CYCLE_TIME; // [us]
    private $structure = null;
    private $structureFile = null;
    private $includeKeys;
    private $includeTimestamp;


    public function __construct($args, $lzy = null)
    {
        if ($lzy !== null) {
            $this->lzy = $lzy;
        } else {
            $this->lzy = new PageFactory();
        }

        $this->sessionId = session_id();

        $this->parseArguments($args);
        if (!$this->dataFile) {
            die("Error: DataStorage2 invoked without dataFile being specified.");
        }

        $this->initLizzyDB();

        if ($this->mode === 'readwrite') {
            $this->openDbReadWrite();
        } else {
            $this->openDbReadOnly();
        }

        $this->initDbTable();
        $this->appPath = getcwd();

        if (isset($_GET['exportStructure'])) {
            $this->exportStructure();
        }

    } // __construct



    public function __destruct()
    {
        if (!isset($this->appPath)) {
            return;
        }
        chdir($this->appPath); // workaround for include bug

        $this->exportToFile(); // saves data if modified

        if (kirby()->session()->get('pfy.debug')) {
            $str = $this->dumpDb(true, false);
            $filename = PFY_LOGS_PATH . "dBdump_{$this->tableName}.txt";
            file_put_contents($filename, $str);
        }

        if ($this->lzyDb) {
            $this->lzyDb->close();
            unset($this->lzyDb);
        }
    } // __destruct



    private function parseArguments($args)
    {
        if (is_string($args)) {
            $args = ['dataFile' => $args];
        }
        $this->dataFile = isset($args['dataFile']) ? $args['dataFile'] :
            (isset($args['dataSource']) ? $args['dataSource'] : ''); // for compatibility
        $this->dataFile = \Usility\PageFactory\resolvePath($this->dataFile);
        $this->logModifTimes = isset($args['logModifTimes']) ? $args['logModifTimes'] : false;
        $this->sid = isset($args['sid']) ? $args['sid'] : '';
        $this->mode = isset($args['mode']) ? $args['mode'] : 'readonly';
        $this->format = isset($args['format']) ? $args['format'] : '';
        $this->supportBlobType = isset($args['supportBlobType']) ? $args['supportBlobType'] : false;
        $this->includeKeys = isset($args['includeKeys']) ? $args['includeKeys'] : false;
        $this->includeTimestamp = isset($args['includeTimestamp']) ? $args['includeTimestamp'] : false;
        $this->secure = isset($args['secure']) ? $args['secure'] : true;
        $this->userCsvFirstRowAsLabels = isset($args['userCsvFirstRowAsLabels']) ? $args['userCsvFirstRowAsLabels'] : true;
        $this->useRecycleBin = isset($args['useRecycleBin']) ? $args['useRecycleBin'] : false;
        $this->format = ($this->format) ? $this->format : pathinfo($this->dataFile, PATHINFO_EXTENSION);
        $this->tableName = isset($args['tableName']) ? $args['tableName'] : '';
        if ($this->tableName && !$this->dataFile) {
            $rawData = $this->lowlevelReadRawData();
            $this->dataFile = PATH_TO_APP_ROOT . $rawData["origFile"];
        }
        $this->resetCache = isset($args['resetCache']) ? $args['resetCache'] : false;
        $this->structureFile = isset($args['structureFile']) ? $args['structureFile'] : false;
        $this->structureDef = isset($args['structureDef']) ? $args['structureDef'] : false;
        $this->exportInternalFields = isset($args['exportInternalFields']) ? $args['exportInternalFields'] : false;
    } // parseArguments




    // === DB level operations ==========================
    public function read( $forceCacheRefresh = true )
    {
        $data = $this->getData($forceCacheRefresh);
        if (!$data) {
            $data = [];
        }
        return $data;
    } // read



    public function readModified( $since )
    {
        $data = $this->getData(true);
        if (!$data) {
            $data = [];
        }
        if (!$since) {
            return $data;
        }
        $since -= 0.01;
        $recKeys = array_keys($this->data);

        $rawLastRecModif = $this->lowlevelReadRawData('recLastUpdates');
        if ($rawLastRecModif && ($rawLastRecModif !== '[]')) {
            $lastRecModifs = $this->jsonDecode($rawLastRecModif);
            $outData = [];
            foreach ($data as $key => $rec) {
//ToDo: fix implementation -> correctly identify recs that have changed since $since
                $outData[$key] = $rec;
//                if (isset($lastRecModifs[ $key ])) {
//                    if ($lastRecModifs[ $key ] > $since) {
//                        $outData[$key] = $rec;
//                    }
//                } else {
//                    $k2 = @$recKeys[ $key ];
//                    if ($k2 && isset($lastRecModifs[ $k2 ])) {
//                        if ($lastRecModifs[ $k2 ] > $since) {
//                            $outData[$key] = $rec;
//                        }
//                    }
//                }
            }
        } else {
            $outData = $data;
        }
        return $outData;
    } // readModified



    // Remember: anybody using write() should do DB-locking explizitly
    public function write($data, $replace = true, $locking = false, $blocking = true, $logModifTimes = false)
    {
        if ($locking && !$this->lockDB( $blocking )) {
            return false;
        } elseif (!$this->_awaitDbLockEnd( $blocking )) {
            return false;
        }

        if ($replace) {
            $res = $this->lowLevelWrite($data);
        } else {
            $res = $this->_updateDB($data);
        }

        if ($locking) {
            $this->unlockDB();
        }
        if ($this->logModifTimes || $logModifTimes) {
            foreach ($data as $recId => $rec) {
                $this->updateRecLastUpdate($recId);
            }
        }

        $this->getData(true);
        return $res;
    } // write




    public function isDbLocked( $checkOnLockedRecords = true, $blocking = false )
    {
        if ($blocking) {
            return !$this->_awaitDbLockEnd( $blocking, $checkOnLockedRecords );

        } else {
            if ($this->_isDbLocked($checkOnLockedRecords)) {
                return true;
            } elseif ($checkOnLockedRecords) {
                return $this->_hasLockedRecords(false);
            }
            return false;
        }
    } // isDbLocked




    public function isLockDB( $checkOnLockedRecords = true, $blocking = false )
    {
        // to be depricated!
        // die("Method isLockDB() has been depricated, use isLockDB() instead");
        return $this->isDbLocked( $checkOnLockedRecords, $blocking );
    } // isLockDB





    public function lockDB( $blocking = true )
    {
        if ($blocking && !$this->_awaitDbLockEnd( $blocking )) {
            return false;

        } elseif ($this->_isDbLocked()) {
            return false;
        }
        return $this->_lockDB();
    } // lockDB




    public function unlockDB($force = false)
    {
        if (!$force && $this->isDbLocked()) {
            return false;
        }
        if ($force) {
            $this->_unlockAllRecs( $force );
        }
        return $this->_unlockDB( $force );
    } // unlockDB



    public function awaitChangedData($lastUpdate, $timeout = false, $pollingSleepTime = false /*us*/)
    {
        $timeout = $timeout ? $timeout : LZY_DEFAULT_DB_TIMEOUT;
        $pollingSleepTime = $pollingSleepTime ? $pollingSleepTime : $this->defaultPollingSleepTime;
        $json = $this->checkNewData($lastUpdate, true);
        if ($json !== null) {
            return $json;
        }
        $tEnd = microtime(true) + $timeout - 0.01;

        while ($tEnd > microtime(true)) {
            $json = $this->checkNewData($lastUpdate, true);
            if ($json !== null) {
                return $json;
            }
            usleep($pollingSleepTime);
        }
        return '';
    } // awaitChangedData




    // === Record level operations ==========================
    // 'Record' defined as first level of multilevel nested data

    public function readRecord($recId)
    {
        $this->getData(true);

        // special case: $this->structure['key'] is defined as '=xy'
        $keyElem = false;
        if (isset($this->structure['key']) && $this->structure['key'] && ($this->structure['key'][0] === '=')) {
            $keyElem = substr($this->structure['key'], 1);
        }

        $recId = $this->fixRecId($recId);
        if (isset($this->data[ $recId ])) { // direct hit:
            $rec = $this->data[ $recId ];
        } else {
            // check whether there's a record with corresponding '_key' field:
            $rec = $this->findRecByContent(REC_KEY_ID, $recId);
        }

        // special case: $this->structure['key'] is defined as '=xy' (2)
        // -> reverse here by copying recId in to element xy
        if ($keyElem && !isset($rec[$keyElem])) {
            $rec[$keyElem] = $recId;
        }
        return $rec;
    } // readRecord



    public function writeRecord($recId, $recData, $locking = true, $blocking = true, $logModifTimes = false)
    {
        if (($recId = $this->fixRecId($recId)) === false) {
            $recId = $this->createNewRecId(); // recId -> append rec
        }

        if ((($recId === '') || ($recId === false)) && @$recData[REC_KEY_ID]) {
            $recId = $recData[REC_KEY_ID];
        }

        // if $blocking=false, _awaitRecLockEnd() performs isRecLocked():
        if (!$this->_awaitRecLockEnd($recId, $blocking, false)) {
            return false;
        }

        if ($locking && !$this->lockRec($recId)) {
            return false;
        }

        $data = $this->getData(true);
        $data[ $recId ] = $recData;

        $this->lowLevelWrite($data);

        if ($this->logModifTimes || $logModifTimes) {
            $this->updateRecLastUpdate( $recId );
        }

        if ($locking) {
            $this->_unlockRec($recId);
        }
        $this->getData(true);
        return true;
    } // writeRecord



    public function addRecord($recData, $recId = false, $locking = true, $blocking = true, $logModifTimes = false)
    {
        if ($locking && $this->isDbLocked( false )) {
            return false;
        }

        if ((($recId === '') || ($recId === false)) && @$recData[REC_KEY_ID]) {
            $recId = $recData[REC_KEY_ID];
        }

        $this->getData(true);
        if (($recId === false) || ($recId === 'new-rec')) { // create new ID if none supplied:
            $recId = $this->createNewRecId( $recId );
        }

        // make sure the new recId is unique:
        while (isset($this->data[ $recId ])) {
            $recId = $this->createNewRecId();
        }

        // copy recId into REC_KEY_ID field:
        $recData[REC_KEY_ID] = $recId;

        // write new rec to DB:
        $this->data[ $recId ] = $recData;
        $this->lowLevelWrite();

        if ($this->logModifTimes || $logModifTimes) {
            $this->updateRecLastUpdate( $recId );
        }
        return true;
    } // addRecord




    public function deleteRecord($recId, $locking = true, $blocking = true)
    {
        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }
        if ($this->isRecLocked( $recId )) {
            return false;
        }

        if (!$this->_awaitRecLockEnd($recId, $blocking)) {
            return false;
        }
        if ($locking) {
            if (!$this->_lockRec($recId)) {
                return false;
            }
        }
        $this->getData(true);

        $res = false;
        if (isset($this->data[ $recId ])) {
            unset($this->data[ $recId ]);
            if ($this->structure['key'] === 'index') {
                $this->data = array_values($this->data);
            }
            $this->lowLevelWrite();
            $res = true;
        } else {
            $this->mylog("### Datastorage:deleteRecord: '$recId' not found");
        }

        if ($locking) {
            $this->_unlockRec($recId);
        }
        return $res;
    } // deleteRecord




    public function lockRec( $recId, $blocking = true, $lockForAll = false )
    {
        if ($this->isDbLocked( false, $blocking )) {
            return false;
        }
        if (($recId = $this->fixRecId($recId, true)) === false) {
            return false;
        }

        if (!$this->_awaitRecLockEnd($recId, $blocking, true)) {
            return false;
        }
        return $this->_lockRec( $recId, $lockForAll );
    } // lockRec




    public function unlockRec( $recId, $force = false )
    {
        if (!$force && $this->isDbLocked( false )) {
            return false;
        }
        if ($recId ==='*') {
            return $this->_unlockAllRecs( $force );
        }

        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }

        $locked = $this->isRecLocked($recId);
        if ($locked && !$force) { // rec already locked
            return false;
        }
        return $this->_unlockRec( $recId, $force );
    } // unlockRec




    public function isRecLocked( $recId, $skipDbLockCheck = false )
    {
        if (!$skipDbLockCheck && $this->_isDbLocked( false )) {
            return true;
        }
        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }
        if (!$this->_isRecLocked( $recId )) {
            return false;
        }
        // lock found, now check timed out?
        $recLocks = $this->lowlevelReadRecLocks();
        if (!$recLocks) {
            return null;
        }
        if (isset($recLocks[$recId])) {
            $locRec = $recLocks[$recId];
            $lockDuration = microtime(true) - $locRec['lockTime'];
            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
                $this->unlockRec($recId, true);
                return false;
            }
            return true; // locked
        }
        return false;
    } // isRecLocked




    public function hasDbLockedRecords( $checkDBlevel = true)
    {
        if ($checkDBlevel && $this->isDbLocked(false)) {
            return true;
        }
        $recLocks = $this->lowlevelReadRecLocks();
        if (!$recLocks) {
            return null;
        }
        $locked = false;
        foreach ($recLocks as $recId => $lockRec) {
            $locked = $this->isRecLocked( $recId );
            if ($locked) {
                break;
            }
        }
        return $locked;
    } // hasDbLockedRecords




    public function getLockedRecords()
    {
        return $this->lowlevelReadRecLocks();
    } // getLockedRecords




    public function getNoOfRecords()
    {
        $this->getData( true );
        if (!$this->data) {
            return 0;
        } else {
            return sizeof($this->data);
        }
    } // getNoOfRecords




    // === Element level operations ==========================
    // Element applies to any level of nested data, in particular below top level (i.e. records):

    public function readElement( $key )
    {
        $this->getData(true);
        if (!$this->data) {
            return null;
        }

        if (strpos($key, '*') !== false) {
            return $this->_readElementGroup( $key );
        }
        if (strpos($key, '#') !== false) {
            return $this->_readNumericKeyElements( $key );
        }

        // access nested element ('d3,d31,d312'):
        $rec = $this->data;
        foreach (\Usility\PageFactory\explodeTrim(',', $key) as $k) {
            $k = trim($k, '\'"');
            if (is_array($rec)) {
                if (isset($rec[$k])) { // direct hit
                    $rec = $rec[$k];
                } elseif (isset(array_keys($rec)[$k])) { // hit via index
                    $rec = $rec[ array_keys($rec)[$k] ];
                } else { // not found
                    $k1 = $this->findArrayElementByAttribute($rec, REC_KEY_ID, $k, true);
                    if ($k1) {
                        $k1 = array_keys($k1)[0];
                        $rec = &$rec[$k1];
                    } else {
                        $rec = null;
                    }
                    break;
                }

            } else {
                $rec = null;
                break;
            }
        }

//??? what was that for...?
//        if (is_array($rec)) {
//            $rec = null;
//        }
        return $rec;
    } // readElement




    public function lastModifiedElement($key)
    {
        if (strpos($key, '*') !== false) {
            return $this->lastDbModified();
        }
        // $recId can be of form 'r,c', if so, we need to drop the part ',c' in order to refer to the record:
        $key = preg_replace('/(,.*)/', '', $key);
        $rawLastRecModif = $this->lowlevelReadRawData('recLastUpdates');
        $lastRecModifs = $this->jsonDecode($rawLastRecModif);
        $lastRecModif = isset($lastRecModifs[ $key ])? $lastRecModifs[ $key ]: false;
        if (!$lastRecModif) {
            $lastRecModif = $this->lastDbModified();
        }
        return floatval($lastRecModif);
    } // lastModifiedElement




    public function writeElement($key, $value, $locking = true, $blocking = true, $logModifTimes = false, $dataKeyOverrideHash = false)
    {
        if (strpos($key, ',') === false) {
            return $this->writeRecord($key, $value, $locking, $blocking, $logModifTimes);
        }

        if ($locking && !$this->lockDB( false, $blocking )) {
            return false;
        } elseif ($this->isDbLocked(false)) {
            return false;
        }
        $this->getData(true);
        if (!$this->data) {
            $this->data = [];
        }
        $createNewKey = false;
        if (substr($key, -1) === '*') {
            $key = substr($key,0,-2);
            $createNewKey = true;
        }
        $rec = &$this->data;

        foreach (\Usility\PageFactory\explodeTrim(',', $key) as $k) {
            $k = trim($k, '\'"');
            if (is_array($rec)) {
                if (isset($rec[$k])) { // direct hit
                    $rec = &$rec[$k];
                } elseif (isset(array_keys($rec)[$k])) { // hit via index
                    $rec = &$rec[ array_keys($rec)[$k] ];
                } else { // not found, create element
                    $k1 = $this->findArrayElementByAttribute($rec, REC_KEY_ID, $k, true);
                    if ($k1) {
                        $k1 = array_keys($k1)[0];
                        if (isset($rec[$k1])) {
                            $rec = &$rec[$k1];
                            $createNewKey = false;
                        } else {
                            $rec[$k] = [];
                            $rec = &$rec[$k1];
                        }
                    } else {
                        if ($dataKeyOverrideHash) {
                            $i = createHash();
                        } else {
                            for ($i = 0; true; $i++) {
                                if (!isset($rec[$i])) {
                                    break;
                                }
                            }
                        }
                        $rec[$i] = [];
                        $rec = &$rec[$i];
                    }
                }

            } else {
                list($r,$c) = \Usility\PageFactory\explodeTrim(',', $key);
                if (!isset($this->data[$r][$c]) || is_array($this->data[$r])) {
                    $this->data[$r][$c] = $value;
                    $rec = &$this->data[$r][$c];
                } else {
                    // Error: parent elem exists, but is of scalar type:
                    $rec = null;
                }
            }
        }

        if ($rec !== null) {
            if ($createNewKey) {
                $rec[] = $value;
            } else {
                $rec = $value;
            }
            $res = $this->lowLevelWrite();

            if ($this->logModifTimes || $logModifTimes) {
                $key = preg_replace('/,.*/', '', $key);
                $this->updateRecLastUpdate( $key );
            }
        }

        if ($locking) {
            $this->unlockDB();
        }
        return $res;
    } // writeElement




    public function deleteElement($key, $locking = true, $blocking = true)
    {
        if (strpos($key, ',') === false) {
            return $this->deleteRecord($key, $locking, $blocking);
        }

        if ($locking && !$this->lockDB( false, $blocking )) {
            return false;
        } elseif ($this->isDbLocked(false)) {
            return false;
        }
        $this->getData(true);
        if (!$this->data) {
            $this->data = [];
        }
        $rec = &$this->data;
        foreach (\Usility\PageFactory\explodeTrim(',', $key) as $k) {
            $k = trim($k, '\'"');
            if (is_array($rec)) {
                if (isset($rec[$k])) { // direct hit
                    $rec = &$rec[$k];
                } elseif (isset(array_keys($rec)[$k])) { // hit via index
                    $rec = &$rec[ array_keys($rec)[$k] ];
                } else { // not found, look for elem with matching _key
                    $k1 = $this->findArrayElementByAttribute($rec, REC_KEY_ID, $k, true);
                    if ($k1 !== false) {
                        $k1 = array_keys($k1)[0];
                        if (isset($rec[$k1])) {
                            unset($rec[$k1]);
                        } else {
                            return "Error..."; //???
                        }
                    } else {
                        return "Error..."; //???
                    }
                }

            } else {
                list($r,$c) = \Usility\PageFactory\explodeTrim(',', $key);
                if (!isset($this->data[$r][$c]) || is_array($this->data[$r])) {
                    unset( $this->data[$r][$c] );
                } else {
                    // Error: parent elem exists, but is of scalar type:
                    $rec = null;
                }
            }
        }

        $res = $this->lowLevelWrite();

        if ($locking) {
            $this->unlockDB();
        }
        return (bool)$res;
    } // deleteElement




    public function findRecByContent($key, $value, $returnKey = false)
    {
        // find rec for which key AND value match
        // returns the record unless $returnKey is true, then it returns the key
        $data = $this->getData();
        if (!$data) {
            return null;
        }
        foreach ($data as $datakey => $rec) {
            if ($value === @$rec[$key]) {
                if ($returnKey) {
                    return $datakey;
                } else {
                    return $rec;
                }
            }
        }
        return null;
    } // findRecByContent




    public function getStructure()
    {
        if (@$this->structure['elements']) {
            if ($this->includeKeys && !isset($this->structure['elements'][REC_KEY_ID])) {
                if ($this->includeTimestamp) {
                    $this->structure['elements'][TIMESTAMP_KEY_ID] = ['type' => 'string'];
                }
                $this->structure['elements'][REC_KEY_ID] = [ 'type' => 'string' ];
            }
        } else {
            $this->determineStructure();
            if (($this->structure !== false) && $this->includeKeys && !isset($this->structure['elements'][REC_KEY_ID])) {
                if ($this->includeTimestamp) {
                    $this->structure['elements'][TIMESTAMP_KEY_ID] = ['type' => 'string'];
                }
                $this->structure['elements'][REC_KEY_ID] = [ 'type' => 'string' ];
            }
        }
        return $this->structure;
    } // getStructure

    // for compatibility: synonyme for getStructure()
    public function getDbRecStructure()
    {
        return $this->getStructure();
    } // getDbRecStructure




    public function setStructure( $structure ) {
        $this->structure = $structure;
        $this->lowLevelWriteStructure();
    } // setStructure



    public function getSourceFilename() {
        return $this->dataFile;
    } // getSourceFilename




    private function exportStructure()
    {
        // skip ticket DB or invoked from backend process or not on localhost:
        if (!isset($GLOBALS['lizzy']['isLocalhost']) ||
            !$GLOBALS['lizzy']['isLocalhost'] ||
            !function_exists('convertToYaml') ||
            (strpos($this->dataFile, '.#tickets') !== false)) {
            return;
        }

        $exportFile = $_GET['exportStructure'];

        $recStructure = $this->getStructure();

        $outArray = [
            'key' => $recStructure[ 'key' ],
            'elements' => $recStructure[ 'elements' ],
        ];

        $srcfile = $this->dataFile;
        $out = "# DB Structure of $srcfile\n\n";
        $out .= convertToYaml( $outArray, 2 );

        if (!$exportFile && ($exportFile !== 'false')) {
            $exportFile = '~/' . \Usility\PageFactory\dir_name($srcfile) .'#'
                .\Usility\PageFactory\base_name($srcfile, false) . '_structure.yaml';
        }
        if ($exportFile) {
            $exportFile = \Usility\PageFactory\resolvePath($exportFile, true);
            file_put_contents($exportFile, $out);
        }

        die("DB Structure written to '$exportFile'.");

    } // exportStructure



    public function lastDbModified()
    {
        $rawData = $this->lowlevelReadRawData();
        $filemtime = (float) filemtime( $this->dataFile );
        $lastModified = $rawData['lastUpdate'];
        if ($filemtime > $lastModified) {
            $lastModified = $filemtime;
            $this->importFromFile();
        }
        return $lastModified;
    } // lastModified




    public function checkNewData($lastUpdate, $returnJson = false)
    {
        // checks whether new data has been saved since the given time:
        $rawData = $this->lowlevelReadRawData();
        if ($rawData['lastUpdate'] > $lastUpdate) {
            $data = $this->getData(true);
            if ($returnJson) {
                $data['__lastUpdate'] = $rawData['lastUpdate'];
                $data = \json_encode($data);
            }
            return $data;
        } else {
            return null;
        }
    } // checkNewData




 // === depricated ======================
    public function doLockDB()  // alias for compatibility
    {
        die("Method lockDB() has been depricated - use lockDB() instead");
        return $this->lockDB();
    } // doLockDB




    public function doUnlockDB()  // alias for compatibility
    {
        die("Method doUnlockDB() has been depricated - use unlockDB() instead");
        return $this->unlockDB();
    } // doUnlockDB



    public function getDbRef()
    {
        die("Method getDbRef() has been depricated");
    } // getDbRef

    


 // === aux methods ======================
    public function dumpDb( $raw = false, $flat = true )
    {
        if ($raw) {
            $d = $this->lowlevelReadRawData();
        } else {
            $d = $this->getData( true );
        }
        $s = \Usility\PageFactory\var_r($d, 'DB "' . basename($this->dataFile).'"', $flat, false);
        $s = str_replace('⌑⌇⌑', '"', $s);
        return $s;
    } // dumpDb



    
    public function getSourceFormat() {
        return $this->format;
    } // getSourceFormat



    public function getNumericRecIndex( $recKey )
    {
        $data = $this->getData();
        $keys = array_keys($data);
        $inx = array_search($recKey, $keys);
        return $inx;
    } // getNumericRecIndex




 // === private methods ===============
    private function getData( $force = false )
    {
        if ($this->data && !$force) {
            return $this->data;
        }
        $rawData = $this->lowlevelReadRawData();
        $json = $rawData['data'];
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = $this->jsonDecode($json);

        if ($this->supportBlobType) {
            $this->importBlobData($data );
        }

        if ($this->includeKeys) {
            if ($data) {
                $rec0 = reset($data);
                if (!isset($rec0[REC_KEY_ID])) {
                    foreach ($data as $key => $rec) {
                        if (is_array($rec)) {
                            if ($this->includeTimestamp) {
                                $data[$key][TIMESTAMP_KEY_ID] = 0;
                            }
                            $data[$key][REC_KEY_ID] = $key;
                        }
                    }
                }
            }
        }
        $this->data = $data;
        return $data;
    } // getData



    // bare-data: excluding keys starting with '_'
    private function getBareData()
    {
        $rawData = $this->lowlevelReadRawData();
        $json = $rawData['data'];
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = $this->jsonDecode($json);
        $rec0 = reset($data);
        if (is_array($rec0)) {
            $s = implode(',', array_keys($rec0));
        } else {
            $s = $rec0;
        }

        // remove elements where key starts with '_':
        if (!$this->exportInternalFields && strpos(",$s", ',_')) {
            foreach ($data as $recKey => $rec) {
                if (is_string($recKey) && $recKey && ($recKey[0] === '_')) {
                    unset($data[ $recKey ]);
                }
                foreach ($rec as $k => $v) {
                    if ($this->includeKeys && ($k === REC_KEY_ID)) {
                        continue;
                    }
                    if ($this->includeTimestamp && ($k === TIMESTAMP_KEY_ID)) {
                        continue;
                    }
                    if (is_string($k) && $k && ($k[0] === '_')) {
                        unset($data[ $recKey ][ $k ]);
                    }
                }
            }
        }
        return $data;
    } // getBareData




    private function resolveElementKey( $id )
    {
        $rec0 = reset( $this->data );
        if (isset($rec0[ $id ])) {
            return $id;
        }
        return @array_keys($rec0)[ $id ];
    } // resolveElementKey




    private function createNewRecId( $default = false )
    {
        if (!isset($this->structure['key'])) {
            if (is_string($default) && strpos('index,numeric,string,hash,date,datetime,unixtime', $default) !== false) {
                $this->structure['key'] = $default;
                $default = false;
            } else {
                $this->structure['key'] = 'index';
            }
        }
        switch ($this->structure['key']) {
            case 'hash':
                return createHash();

            case 'index':
                $data = $this->getData(true);
                return is_array($data) ? sizeof($this->getData(true)) : 0;

            case 'numeric':
            case 'number':
                $inx = 0;
                foreach ($this->getData() as $key => $rec) {
                    if (is_int($key)) {
                        $inx = max($inx, $key);
                    }
                }
                return ($inx + 1);

            case 'date':
                return date('Y-m-d');

            case 'datetime':
                return date('Y-m-d H:i:s');

            case 'unixtime':
                return time();

            default: // for everything else we use hash, unless default given:
                return $default ? $default : createHash();
        }
    } // createNewRecId




    private function _awaitDbLockEnd($timeout = true, $checkOnLockedRecords = true)
    {
        if (!$timeout) {
            return !$this->_isDbLocked($checkOnLockedRecords);
        }

        // wait for DB to be unlocked:
        if ($timeout === true) {
            $timeout = LZY_DEFAULT_DB_TIMEOUT;
        } else {
            $timeout = min(LZY_LOCK_ALL_DURATION_DEFAULT, $timeout);
        }
        if (@$this->lzy->config->debug_debugLogging && $this->_isDbLocked( $checkOnLockedRecords )) {
            $this->mylog('datastorage:_awaitDbLockEnd()');
        }
        $till = microtime(true) + $timeout;
        while (($locked = $this->_isDbLocked( $checkOnLockedRecords )) && $timeout && (microtime(true) < $till)) {
            usleep($this->defaultPollingSleepTime);
        }
        return !$locked;
    } // _awaitDbLockEnd





    private function _readElementGroup( $key )
    {
        // syntax variant 'd3,d31,d312'
        if (strpos($key, ',') === false) {
            return null;
        }

        $rec = $this->data;
        $keys = \Usility\PageFactory\explodeTrim(',', $key);
        foreach ($keys as $k) {
            array_shift($keys);
            $k = trim($k, '\'"');
            if ($k === '*') {
                $outRecs = [];
                foreach ($rec as $k0 => $subRec) {
                    foreach ($keys as $k1) {
                        $n = intval($k1);
                        if ($n || ($k1 === '0')) {
                            $k1 = $n;
                        }
                        if (isset($subRec[$k1])) {
                            $outRecs[$k0] = $subRec[$k1];
                        } else {
                            $outRecs[$k0] = '';
                        }
                    }
                }
                return $outRecs;
            } else {
                $n = intval($k);
                if ($n || ($k === '0')) {
                    $k = $n;
                }
                if (isset($rec[$k])) {
                    $rec = $rec[$k];
                } else {
                    return null;
                }
            }
        }
        return $rec;
    } // _readElementGroup




    private function _readNumericKeyElements( $key )
    {
        // syntax variant 'd3,d31,d312'
        if (strpos($key, ',') === false) {
            return null;
        }

        $rec = $this->data;
        $keys = \Usility\PageFactory\explodeTrim(',', $key);
        foreach ($keys as $k) {
            array_shift($keys);
            $k = trim($k, '\'"');
            if ($k === '#') {
                $outRecs = [];
                foreach ($rec as $kk => $subRec) {
                    if (!is_numeric($kk)) {
                        continue;
                    }
                    $outRecs[] = $subRec;
                }
                return $outRecs;
            } else {
                $n = intval($k);
                if ($n || ($k === '0')) {
                    $k = $n;
                }
                if (isset($rec[$k])) {
                    $rec = $rec[$k];
                } else {
                    return null;
                }
            }
        }
        return $rec;
    } // _readNumericKeyElements




    private function _isDbLocked( $checkOnLockedRecords = true )
    {
        $rawData = $this->lowlevelReadRawData();
        $lockTime = $rawData['lockTime'];
        if ($lockTime && ($lockTime < (microtime(true) - LZY_LOCK_ALL_DURATION_DEFAULT))) {
            // lock too old - force it open:
            $this->_unlockDB();

        } elseif ($rawData['lockedBy'] &&
            ($rawData['lockedBy'] !== $this->sessionId)) {
            // locked by someone else:
            return true;
        }

        if ($checkOnLockedRecords) {
            return $this->_hasLockedRecords();
        } else {
            return false;
        }
    } // _isDbLocked




    private function _lockDB()
    {
        $rawData = $this->lowlevelReadRawData();
        if ($rawData['lockedBy'] && ($rawData['lockedBy'] !== $this->sessionId)) {
            return false;
        }
        $rawData['lockedBy'] = $this->sessionId;
        $rawData['lockTime'] = microtime(true);
        $this->updateRawDbMetaData($rawData);
        return true;
    } // _lockDB




    private function _unlockDB( $force = false )
    {
        $rawData = $this->lowlevelReadRawData();
        if (!$force && ($rawData['lockedBy'] && ($rawData['lockedBy'] !== $this->sessionId))) {
            return false;
        }
        $rawData['lockedBy'] = '';
        $rawData['lockTime'] = 0.0;
        $this->updateRawDbMetaData($rawData);
        return true;
    } // _unlockDB



    private function _awaitRecLockEnd($recId, $timeout, $checkOnLockedRecords = true)
    {
        if (!$timeout) {
            return !$this->isRecLocked($recId);
        }

        // wait for DB to be unlocked:
        if (!$this->_awaitDbLockEnd($timeout, false)) {
            return false;
        }
        if (@$this->lzy->config->debug_debugLogging && $this->_isDbLocked( $checkOnLockedRecords )) {
            $this->mylog('datastorage:_awaitRecLockEnd()');
        }
        $till = microtime(true) + $timeout;
        while (($locked = $this->_isRecLocked($recId)) && (microtime(true) < $till)) {
            usleep($this->defaultPollingSleepTime);
        }
        return !$locked;
    } // _awaitRecLockEnd




    private function _isRecLocked( $recId )
    {
        //$mySessId = $this->sessionId;
        $recLocks = $this->lowlevelReadRecLocks();
        if (!$recLocks) {
            return null;
        }
        if (isset($recLocks[$recId])) {
            $lockRec = $recLocks[$recId];
            // not locked, if it's my own lock:
            if ($this->isMySessionID( $lockRec['lockOwner'] )) {
                return false; // not locked
            }

            // check whether lock timed out:
            $lockDuration = microtime(true) - $lockRec['lockTime'];
            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
                $this->_unlockRec($recId, true);
                \Usility\PageFactory\mylog("DataStorage: recLoc on $this->dataFile => $recId timed out -> forced open");
                return false;
            }
            // it's locked by somebody else:
            return true; // locked
        }
        return false;
    } // isRecLocked




    private function _hasLockedRecords()
    {
        $recLocks = $this->lowlevelReadRecLocks();
        if (!$recLocks) {
            return null;
        }
        $locked = false;
        foreach ($recLocks as $recId => $lockRec) {
            $lockDuration = microtime(true) - $lockRec['lockTime'];
            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
                // rec-lock too old, force it open:
                $this->_unlockRec($recId);
                continue;
            }
            // not locked, if it's my own lock:
            if ($this->isMySessionID( $lockRec['lockOwner'] )) {
                continue;
            }
            // it's locked by somebody else:
            $locked = true; // locked
            break;
        }
        return $locked;
    } // hasLockedRecords




    private function _lockRec( $recId, $lockForAll = false )
    {

        if ($this->isRecLocked( $recId )) { // rec already locked
            return false;
        }
        $recLocks = $this->lowlevelReadRecLocks();
        $recLocks[$recId] = [
            'lockTime' => microtime(true),
            'lockOwner' => $lockForAll? 0 : $this->sessionId
        ];
        $this->lowLevelWriteRecLocks($recLocks);
        return true;
    } // _lockRec




    private function _unlockRec( $recId, $force = false )
    {
        $recLocks = $this->lowlevelReadRecLocks();
        if ($recId === '*') {
            return $this->_unlockAllRecs( $force );

        } elseif (isset($recLocks[$recId])) {
            if (!$force && !$this->isMySessionID( $recLocks[$recId]['lockOwner'] )) {
                return false;
            }
            unset($recLocks[$recId]);
        }
        $this->lowLevelWriteRecLocks($recLocks);
        return true;
    } // _unlockRec



    private function _unlockAllRecs( $force )
    {
        //$mySessId = $this->sessionId;
        if ( $force ) {
            $this->lowLevelWriteRecLocks( [] );
        } else {
            $recLocks = $this->lowlevelReadRecLocks();
            foreach ($recLocks as $recId => $recLock) {
                if (!$this->isMySessionID($recLocks[$recId]['lockOwner'])) {
                    continue;
                }
                unset($recLocks[$recId]);
            }
            $this->lowLevelWriteRecLocks($recLocks);
        }
        return true;
    } // _unlockAllRecs




    // merge with new data:
    private function _updateDB($newData)
    {
        $data = $this->getData(true);
        if ($data) {
            $newData = array_merge($data, $newData);
        }
        $this->lowLevelWrite($newData);
    } // _updateDB




    private function fixRecId($recId)
    {
        // returns false if recId not existing in DB
        $recId1 = $recId;
        if (is_array($recId)) {
            die("Error in writeRecord: args packed into redID is depricated.");
        }

        if (!$this->data || isset($this->data[ $recId ])) { // direct hit, done:
            return $recId;
        }

        // check case of hash (ignoring poss. ':form1'-type extension):
        if (preg_match('/^([A-Z0-9]{4,20}):?/', $recId, $m)) {
            $recId1 = $m[1];
            if ($recId1) {
                foreach ($this->data as $id => $rec) {
                    if (@$rec[REC_KEY_ID] === $recId1) {
                        return $id;
                    }
                }
            }
        }

        if (preg_match('/\D/', $recId)) {     // it's a string:
            // we need rec-level key, so if it contains further indexes, cut them away:
            if (is_string($recId) && (strpos($recId, ',') !== false)) {
                $recId1 = preg_replace('/,.*/', '', $recId);
            }
        }
        if (!isset($this->data[$recId1]) && is_numeric($recId1)) {
            // if it's numeric, check whether it corresponds to the ordinal index within DB:
            $keys = array_keys( $this->data );
            if (isset($keys[ $recId1 ])) {
                $recId1 = $keys[ $recId1 ];
            }
        }
        return $recId1;
    } // fixRecId




    private function _recIdFromElementKey($key )
    {
        if (strpos($key, ',') !== false) {
            $a = explode(',', $key);
            $key = $a[0];
        }
        return $key;
    } // _recIdFromElementKey



 // === Low Level Operations ===========================================================
    private function lowlevelReadRecLocks()
    {
        $query = "SELECT \"recLocks\" FROM \"{$this->tableName}\"";
        $rawData = $this->lzyDb->querySingle($query, true);
        return isset($rawData['recLocks']) ? $this->jsonDecode($rawData['recLocks']) : false;
    } // lowlevelReadMetaData




    private function lowlevelReadRecLastUpdates()
    {
        $query = "SELECT \"recLastUpdates\" FROM \"{$this->tableName}\"";
        $rawData = $this->lzyDb->querySingle($query, true);
        return isset($rawData['recLastUpdates']) ? $this->jsonDecode($rawData['recLastUpdates']): false;
    } // lowlevelReadRecLastUpdates




    private function lowlevelReadRawData($rawElem = false)
    {
        if (!$this->tableName) {
            return null;
        }
        $query = "SELECT * FROM \"{$this->tableName}\"";
        $rawData = $this->lzyDb->querySingle($query, true);
        if ($rawElem) {
            if (isset($rawData[$rawElem])) {
                return $rawData[$rawElem];
            } else {
                return null;
            }
        }
        $this->rawData = $rawData;
        return $rawData;
    } // lowlevelReadRawData




    private function lowLevelWrite($newData = null, $isJson = false)
    {
        $this->openDbReadWrite();

        if ($this->supportBlobType) {
            $newData = $this->exportBlobData( $newData );
        }

        $json = $this->jsonEncode($newData, $isJson);
        $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem

        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json", 
    "lastUpdate" = $modifTime;

EOT;

        $res = $this->lzyDb->query($sql);
        $this->rawData = $this->lowlevelReadRawData();

        if ($this->structure === null) {
            $this->structure = $this->deriveStructureFromData();
            $this->lowLevelWriteStructure();
        }

        $this->exportRequired = true;
        return $res;
    } // lowLevelWrite



    private function exportBlobData( $data )
    {
        if ($data !== null) {
            $this->data = $data;
        }
        $data = &$this->data;

        foreach ($data as $key => $rec) {
            if (is_array( $rec )) {
                foreach ($rec as $k) {
                    if ((@$k[0] === '~') && ($data[ $key ][ $k ] !== '##BLOB_IN_FILE##')) {
                        $file = \Usility\PageFactory\resolvePath( $k );
                        if (!file_exists( $file )) {
                            \Usility\PageFactory\preparePath( $file );
                        }
                        file_put_contents( $file, $data[ $key ][ $k ] );
                        $data[ $key ][ $k ] = '##BLOB_IN_FILE##';
                    }
                }
            }
            if ((@$key[0] === '~') && ($data[ $key ] !== '##BLOB_IN_FILE##')) {
                $file = \Usility\PageFactory\resolvePath( $key );
                if (!file_exists( $file )) {
                    \Usility\PageFactory\preparePath( $file );
                }
                file_put_contents( $file, $data[ $key ] );
                $data[ $key ] = '##BLOB_IN_FILE##';
            }
        }
        return null;
    } // exportBlobData



    private function importBlobData( &$data )
    {
        if (is_array( $data )) {
            foreach ($data as $key => $rec) {
                if (is_array($rec)) {
                    foreach ($rec as $k) {
                        if (@$k[0] === '~') {
                            $file = \Usility\PageFactory\resolvePath($k);
                            if (file_exists($file)) {
                                $data[$key][$k] = file_get_contents($file);
                            }
                        }
                    }
                }
                if (@$key[0] === '~') {
                    $file = \Usility\PageFactory\resolvePath($key);
                    if (file_exists($file)) {
                        $data[$key] = file_get_contents($file);
                    }
                }
            }
        }
    } // importBlobData



    private function lowLevelWriteRecLocks( $recLocks )
    {
        $this->openDbReadWrite();

        $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem
        $recLocksJson = $this->jsonEncode( $recLocks );
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "lastUpdate" = $modifTime,
    "recLocks" = "$recLocksJson";

EOT;

        $res = $this->lzyDb->query($sql);
        return $res;
    } // lowLevelWriteMeta



    private function lowLevelWriteStructure()
    {
        $this->openDbReadWrite();

        $structureJson = isset($this->structure)? $this->jsonEncode($this->structure): '';

        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "structure" = "$structureJson";

EOT;

        $res = $this->lzyDb->query($sql);
        return $res;
    } // lowLevelWriteStructure




    private function updateRawDbMetaData($rawData)
    {
        $this->openDbReadWrite();

        $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem
        $lockTime = str_replace(',', '.', $rawData['lockTime']); // fix float->str conversion problem
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "lockedBy" = "{$rawData['lockedBy']}",
    "lockTime" = "$lockTime",
    "lastUpdate" = $modifTime;

EOT;
        $res = $this->lzyDb->query($sql);
        $this->rawData = $this->lowlevelReadRawData();
    } // updateRawDbMetaData




    private function updateDbModifTime( $modifTime = false )
    {
        $this->openDbReadWrite();

        if (!$modifTime) {
            $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem
        }
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "lastUpdate" = $modifTime;

EOT;
        $res = $this->lzyDb->query($sql);
        $this->rawData = $this->lowlevelReadRawData();
    } // updateDbModifTime




    private function updateRecLastUpdate( $recId )
    {
        // $recId can be of form 'r,c', if so, we need to drop the part ',c' in order to refer to the record:
        $recId = preg_replace('/(,.*)/', '', $recId);

        $query = "SELECT \"recLastUpdates\" FROM \"{$this->tableName}\"";
        $rawData = $this->lzyDb->querySingle($query, true);
        $recLastUpdates = $this->jsonDecode($rawData['recLastUpdates']);

        $recLastUpdates[ $recId ] = microtime(true);

        $this->lowlevelWriteRecLastUpdates( $recLastUpdates );
        return true;
    } // updateRecLastUpdate




    private function lowlevelWriteRecLastUpdates( $lastRecUpdates )
    {
        $this->openDbReadWrite();

        $json = $this->jsonEncode( $lastRecUpdates );
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "recLastUpdates" = "$json";

EOT;
        $this->lzyDb->query($sql);
    } // lowlevelWriteRecLastUpdates




    private function initLizzyDB()
    {
        if (!file_exists(LIZZY_DB)) {
            touch(LIZZY_DB);
        }
    } // initLizzyDB




    private function openDbReadWrite()
    {
        if ($this->dbModeRW) {
            return;
        }
        $this->_openDbReadWrite();
        $this->dbModeRW = true;
    } // openDbReadWrite

    private function _openDbReadWrite()
    {
        if ($this->lzyDb) {
            $this->lzyDb->close();
        }
        $this->lzyDb = new \SQLite3(LIZZY_DB, SQLITE3_OPEN_READWRITE);
        $this->lzyDb->busyTimeout(5000);
        $this->lzyDb->exec('PRAGMA journal_mode = wal;'); // https://www.php.net/manual/de/sqlite3.exec.php
    } // _openDbReadWrite




    private function openDbReadOnly()
    {
        if ($this->lzyDb) {
            // if it's already open, leave it open, even it's in read-write mode:
            return;
        }
        $this->lzyDb = new \SQLite3(LIZZY_DB, SQLITE3_OPEN_READONLY);
        $this->lzyDb->busyTimeout(5000);
        $this->dbModeRW = false;
    } // openDbReadWrite





    private function initDbTable()
    {
        // 'dataFile' refers to a yaml or csv file that contains the original data source
        // each dataFile is copied into a table within the lizzyDB

        // access to files in config/ are risky -> restrict access to admins:
        if ($this->secure && (strpos($this->dataFile, 'config/') !== false)) {
            $permission = @$_SESSION['lizzy']['configDbPermission'];
            if (!$permission && @$_SESSION['lizzy']['isLocalhost']) {
                $permission = true;
                if ($this->lzy) {
                    $this->lzy->page->addMessage('{{ lzy-config-db-permission-localhost-warning }}');
                }
            }
            if (!$permission) {
                $who = @$this->config->admin_configDbPermission;
                $this->mylog("DataStorage: access to DB '$this->dataFile' in config/ folder denied. User would need to be '$who'.");
                if ($GLOBALS['lizzy']['isLocalhost']) {
                    die("DataStorage: access to DB '$this->dataFile' in config/ folder denied. You'd have to be '$who'.");
                } else {
                    return null;
                }
            }
        }

        // check data file
        $dataFile = $this->dataFile;
        if ($this->resetCache) {
            touch($dataFile);
        }

        if ($dataFile && !file_exists($dataFile)) {
            $path = pathinfo($dataFile, PATHINFO_DIRNAME);
            if (!file_exists($path) && $path) {
                if (!mkdir($path, 0777, true) || !is_writable($path)) {
                    if (function_exists('fatalError')) {
                        \Usility\PageFactory\fatalError("DataStorage: unable to create file '{$dataFile}'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                    } else {
                        die("DataStorage: unable to create file '{$dataFile}'");
                    }
                }
            }
            touch($dataFile);
        }

        // check whether dataFile-table exists:
        if ($this->tableName) {
            $tableName = $this->tableName;

        } elseif (!$this->dataFile) { // neither file- nor tablename -> nothing to do
            return;

        } else {
            $tableName = $this->deriveTableName();
            $this->tableName = $tableName;
        }
        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName';";
        if (is_bool($sql)) {
            return;
        }
        $stmt = $this->lzyDb->prepare($sql);
        if (is_bool($stmt)) {
            return;
        }
        $res = $stmt->execute();
        $table = $res->fetchArray(SQLITE3_ASSOC);
        if (!$table) {  // if table does not exist: create it and populate it with data from origFile
            $this->createNewTable($tableName);
            $rawData = $this->lowlevelReadRawData();

        } else { // if table exists, check whether update necessary:
            $ftime = floatval(filemtime($dataFile));
            $rawData = $this->lowlevelReadRawData();
            if ($ftime > $rawData['lastUpdate']) {
                $res = $this->importFromFile();
                if ($res === false) {
                    die("Error: unable to update table in lzyDB: '$tableName'");
                }
            } else {
                $this->getData();
            }
        }
        if (!$rawData['structure'] || @$this->structureFile || @$this->structureDef) {
            $this->determineStructure();
            $this->lowLevelWriteStructure();
        } else {
            $this->structure = $this->jsonDecode($rawData['structure']);
        }

        return;
    } // initDbTable




    private function importFromFile($initial = false)
    {
        $this->openDbReadWrite();
        $rawData = $this->loadFile();

        if ($this->logModifTimes) {
            $oldDat = $this->getData();
            $newData = $this->decode($rawData, false, false, $initial);
            foreach ($newData as $key => $rec) {
                if ($rec !== $oldDat[$key]) {
                    $this->updateRecLastUpdate( $key );
                }
            }
            $json = $this->jsonEncode($newData, false);

        } else {
            $json = $this->decode($rawData, false, true, $initial);
            $json = $this->jsonEncode($json, true);
        }

        $json = \SQLite3::escapeString($json);
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json";

EOT;
        $this->lzyDb->query($sql);
        $this->updateDbModifTime();
        $this->rawData = $this->lowlevelReadRawData();

        $this->checkExternalStructureDef();
        return true;
    } // importFromFile




    private function exportToFile()
    {
        $rawData = $this->lowlevelReadRawData();
        if (!$this->exportRequired) {
            return;
        }

        if (@$GLOBALS['appRoot'] && @$rawData['origFile']) {
            $filename = $GLOBALS['appRoot'] . $rawData['origFile'];

        } else {
            $filename = PATH_TO_APP_ROOT . $rawData['origFile'];
        }
        if (!$filename) {
            $this->mylog("Error: filename missing for export file (".__FILE__.' '.__LINE__.')');
            return;
        }
        if (!file_exists($filename)) {
            $this->mylog("Error: unable to export data to file '$filename'");
            return;
        }

        $data = $this->getBareData();

        if (!$this->includeKeys) {
            foreach ($data as $recKey => $rec) {
                if (isset($rec[REC_KEY_ID])) {
                    unset( $data[$recKey][REC_KEY_ID]);
                }
                if (isset($rec[TIMESTAMP_KEY_ID])) {
                    unset( $data[$recKey][TIMESTAMP_KEY_ID]);
                }
            }
        }

        if ($this->format === 'yaml') {
            $this->writeToYamlFile($filename, $data);

        } elseif ($this->format === 'json') {
            file_put_contents($filename, \json_encode($data));

        } elseif ($this->format === 'csv') {
            $this->writeToCsvFile($filename, $data);
        }
        $this->exportRequired = false;
    } // exportToFile




    private function writeToYamlFile($filename, $data)
    {
        $yaml = Yaml::encode($data, 3);

        // retrieve header from original file:
        $lines = file($filename);
        $hdr = '';
        foreach ($lines as $line) {
            if ($line && ($line[0] !== '#')) {
                break;
            }
            $hdr .= "$line\n";
        }
        file_put_contents($filename, $hdr.$yaml);
    } // writeToYamlFile




    private function writeToCsvFile($filename, $array, $quote = '"', $delim = ';', $forceQuotes = true)
    {
        $out = '';
        $outData = [];
        if (!is_array($array)) {
            return false;
        }
        // prepend header row:
        $structure = $this->getStructure();
        if (isset($structure['elements'])) {
            $elemKeys = array_keys($structure['elements']);
            if (!$this->includeKeys) {
                $i = array_search(REC_KEY_ID, $elemKeys);
                if ($i !== false) {
                    unset($elemKeys[$i]);
                }
                $i = array_search(TIMESTAMP_KEY_ID, $elemKeys);
                if ($i !== false) {
                    unset($elemKeys[$i]);
                }
            }
            $outData[0] = array_values($elemKeys);
        }
        // remove field labels:
        foreach ($array as $row) {
            $outData[] = array_values($row);
        }
        // transform into CSV format:
        foreach ($outData as $row) {
            if (!is_array($row)) { continue; }
            foreach ($row as $i => $elem) {
                if (is_array($elem)) {
                    $elem = @$elem[0]; // -> see note at top
                }
                if ($forceQuotes || strpbrk($elem, "$quote$delim")) {
                    $row[$i] = $quote . str_replace($quote, $quote.$quote, $elem) . $quote;
                }
                $row[$i] = str_replace(["\n", "\r"], ["\\n", ''], $row[$i]);
            }
            $out .= implode($delim, $row)."\n";
        }
        file_put_contents($filename, $out);
    } // writeToCsvFile




    private function jsonEncode($data = null, $isAlreadyJson = false)
    {
        if ($data === null) {
            $data = $this->data;
        }
        if ($isAlreadyJson && is_string($data)) {
            $json = $data;
        } else {
            $json = \json_encode($data);
        }
        $json = str_replace(['"', "'"], ['⌑⌇⌑', '⌑⊹⌑'], $json);
        return $json;
    } // jsonEncode




    private function jsonDecode($json)
    {
        if (!is_string($json)) {
            return null;
        }
        $json = str_replace(['⌑⌇⌑', '⌑⊹⌑'], ['"', "'"], $json);
        return \json_decode($json, true);
    } // jsonDecode




    private function decode($rawData, $fileFormat = false, $outputAsJson = false, $analyzeStructure = false)
    {
        if (!$rawData) {
            return null;
        }
        if (!$fileFormat) {
            $fileFormat = $this->format;
        }
        if ($fileFormat === 'json') {
            $rawData = str_replace(["\r", "\n", "\t"], '', $rawData);
            $data = $this->jsonDecode($rawData);

        } elseif ($fileFormat === 'yaml') {
            $data = $this->convertYaml($rawData);

        } elseif (($fileFormat === 'csv') || ($this->format === 'txt')) {
            $data = $this->parseCsv($rawData);

            // if not suppressed: use first data row as element-labels:
            if ($this->userCsvFirstRowAsLabels) {
                $elemKeys = array_shift( $data );
                foreach ($elemKeys as $k => $elemKey) {
                    $name = \Usility\PageFactory\translateToIdentifier($elemKey, false, true, false);
                    $this->structure['elements'][$elemKey] = ['type' => 'string', 'name' => $name, 'formLabel' => $elemKey];
                }
                $keyAvailable = isset($elemKeys[REC_KEY_ID]);
                if ($this->includeKeys) {
                    $this->structure['key'] = 'hash';
                } else {
                    $this->structure['key'] = 'index';
                }
                $out = [];
                foreach ($data as $r => $rec) {
                    if ($keyAvailable || $this->includeKeys) {
                        $r = @$elemKeys[REC_KEY_ID]? $elemKeys[REC_KEY_ID]: createHash();
                    }
                    foreach ($elemKeys as $i => $elemKey) {
                        $out[$r][$elemKey] = $rec[$i];
                    }
                }
                $data = $out;
            }

        } else {    // unknown fileType:
            $data = false;
        }

        if (!$data) {
            $data = array();
        }

        if ($this->includeKeys) {
            foreach ($data as $key => $rec) {
                if ($this->includeTimestamp) {
                    if (isset($data[$key][REC_KEY_ID])) {
                        unset($data[$key][REC_KEY_ID]);
                    }
                    if (!isset( $data[ $key ][ TIMESTAMP_KEY_ID ] ) || !$data[ $key ][ TIMESTAMP_KEY_ID ]) {
                        $data[$key][TIMESTAMP_KEY_ID] = 0;
                    }
                    $data[$key][REC_KEY_ID] = $key;
                } elseif (!isset( $data[ $key ][ REC_KEY_ID ] ) || !$data[ $key ][ REC_KEY_ID ]) {
                    $data[$key][REC_KEY_ID] = $key;
                }
            }
        }

        $this->lowLevelWrite( $data );
        $this->data = $data;
        $this->determineStructure();
        if ($outputAsJson) {
            return $this->jsonEncode();
        }
        return $data;
    } // decode




    private function checkExternalStructureDef()
    {
        if (@$this->structureDef) {
            $this->structure = $this->structureDef;
            unset($this->structureDef);
            return $this->structure;
        }
        $structure = false;
        if ($this->structureFile) {
            $structureFile = \Usility\PageFactory\resolvePath($this->structureFile);
        } else {
            $structureFile = \Usility\PageFactory\fileExt($this->dataFile, true) . '_structure.yaml';
        }

        if (file_exists( $structureFile )) {
            $structure = \Usility\PageFactory\loadFile( $structureFile );
//            $structure = \Usility\PageFactory\getYamlFile( $structureFile );

    //        } elseif (isset($this->data['_structure'])) {
    //            // structure may be submitted within data in a special record '_structure':
    //            $structure = $this->data['_structure'];
    //            unset($this->data['_structure']);
        }
        return $structure;
    } // checkExternalStructureDef



    private function determineStructure()
    {
        $structure = $this->checkExternalStructureDef();

        // make sure type for rec-level keys is set:
        if (!isset($structure['elements']) || !$structure['elements']) {
            // no struct info available - try to derive it from data:
            if (!$this->data) {
                $this->structure = false;
                return false; // no data, try again later...
            }
            $structure = $this->deriveStructureFromData();
        }


        // make sure structure is complete:
        if (!isset($structure['elements']) || !$structure['elements']) { // fields missing
            $this->structure = $structure;
            return $structure;
        }

        // add 'name' elem:
        $rec0 = reset($structure['elements']);
        if (!isset($rec0['name']) || !$rec0['name']) {
            foreach ($structure['elements'] as $elemKey => $rec) {
                $structure['elements'][$elemKey]['type'] = @$rec['type']? $rec['type']: 'string';
                $structure['elements'][$elemKey]['name'] = \Usility\PageFactory\translateToIdentifier($elemKey, false, true, false);
                $structure['elements'][$elemKey]['formLabel'] = @$rec['formLabel']? $rec['formLabel']: $elemKey;
            }
        }

        // make sure type for rec-level keys is set:
        if (!isset($structure['key'])) {
            $structure['key'] = 'index';
        }

        $this->structure = $structure;
        return $structure;
    } // determineStructure




    private function deriveStructureFromData()
    {
        $rawData = $this->lowlevelReadRawData();
        $structure = [ 'key' => false, 'elements' => [] ];
        if (!$rawData[ 'data']) {
            return $structure;
        }
        $key0 = false;
        $rec0 = false;
        $keyType = false;

        if (($this->format === 'yaml') || ($this->format === 'json')) {
            // try to figure out keyType:
            if (!isset($structure['key']) || ($structure['key'] === false)) {
                if (isset($rawData['origFile'])) {
                    $rawData = \Usility\PageFactory\getFile( PATH_TO_APP_ROOT.$rawData['origFile'], 'all', true);
                }
                if ($rawData) {
                    if ((($this->format === 'yaml') && ($rawData[0] === '-')) ||
                        (($this->format === 'json') && ($rawData[0] === '['))) {
                        $keyType = 'index';
                        $structure['key'] = 'index';
                    } elseif (($this->format === 'yaml') && preg_match('/^[A-Z0-9]{4,20}/', $rawData)) {
                        $keyType = 'hash';
                        $structure['key'] = 'hash';
                    }
                }
            }
            // get first regular record:
            $data = $this->getData();
            if ($data) {
                foreach ($data as $k => $rec) { // skip meta elements...
                    if (!is_string($k) || (@$k[0] !== '_')) {
                        break;
                    }
                }
                $rec0 = $rec;
                $key0 = $k;
            }

        } elseif ($this->format === 'csv') {    // csv
            $keyType = 'index';
            $structure['key'] = 'index';
            $data = $this->getData();
            if ($data) {
                $rec0 = reset($data);
                $key0 = 0;
            }

        } else {
            die("Error in deriveStructureFromData(): file format '$this->format' unknown");
        }

        if (!$rec0) {
            $data = $this->getData();
            $rec0 = reset( $data );
        }
        if (!is_array( $rec0 )) {
            return $structure;
        }

        foreach ($rec0 as $elemKey => $val) {
            $type = 'string';
            if (is_numeric($val)) {
                $type = 'numeric';
            } elseif (is_bool($val)) {
                $type = 'bool';
            }
            $structure['elements'][$elemKey]['type'] = $type;
            $structure['elements'][$elemKey]['name'] = \Usility\PageFactory\translateToIdentifier($elemKey, false, true, false);
            $structure['elements'][$elemKey]['formLabel'] = $elemKey;
        }

        if (!$structure['key']) {
            if (preg_match('/^[A-Z][A-Z0-9]{4,20}/', $key0)) {
                $structure['key'] = 'hash';
            } elseif (preg_match('/^ \d{2,4} - \d\d - \d\d/x', $key0)) {
                $structure['key'] = 'date';
            } elseif (preg_match('/^ \d{2,4} - \d\d - \d\d \d\d : \d\d (: \d\d)? /x', $key0)) {
                $structure['key'] = 'datetime';
            } elseif (preg_match('/\D/', $key0)) {
                $structure['key'] = $keyType? $keyType: 'string';
            } elseif (intval($key0) > 946681200) { // 2000-01-01
                $structure['key'] = 'unixtime';
            } else {
                $structure['key'] = 'numeric';
            }
        }

        return $structure;
    } // deriveStructureFromData




    private function convertYaml($str)
    {
        $data = null;
        if ($str) {
            $str = str_replace("\t", '    ', $str);
            $data = Yaml::decode($str);
        }
        return $data;
    } // convertYaml




    private function parseCsv($str, $delim = false, $enclos = false) {

        if (!$delim) {
            $delim = (substr_count($str, ',') > substr_count($str, ';')) ? ',' : ';';
            $delim = (substr_count($str, $delim) > substr_count($str, "\t")) ? $delim : "\t";
        }
        if (!$enclos) {
            if (strpbrk($str[0], '"\'')) {
                $enclos = $str[0];
            } else {
                $enclos = (substr_count($str, '"') > substr_count($str, "'")) ? '"' : "'";
            }
        }

        $lines = explode(PHP_EOL, $str);
        $array = array();
        foreach ($lines as $line) {
            if (!$line) { continue; }
            $line = str_replace("\\n", "\n", $line);
            $array[] = str_getcsv($line, $delim, $enclos);
        }
        return $array;
    } // parseCsv



    private function getSessionID()
    {
        return $this->sessionId;
    } // getSessionID




    private function isMySessionID( $sid )
    {
        return ($sid === $this->sessionId);
    } // isMySessionID




    private function deriveTableName()
    {
        $tableName = str_replace(['/', '.'], '_', $this->dataFile);
        $tableName = preg_replace('|^[./_]*|', '', $tableName);
        return $tableName; // remove leading '../...'
    } // deriveTableName




    private function createNewTable($tableName)
    {
        $this->openDbReadWrite();

        $sql = "CREATE TABLE IF NOT EXISTS \"$tableName\" (";
        $sql .= '"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,';
        $sql .= '"data" VARCHAR, "structure" VARCHAR, "origFile" VARCHAR, "recLastUpdates" VARCHAR, "recLocks" VARCHAR, "lockedBy" VARCHAR, "lockTime" REAL, "lastUpdate" REAL)';
        $res = $this->lzyDb->query($sql);
        if ($res === false) {
            die("Error: unable to create table in lzyDB: '$tableName'");
        }

        $origFileName = $this->dataFile;
        if (PATH_TO_APP_ROOT && (strpos($origFileName, PATH_TO_APP_ROOT) === 0)) {
            $origFileName = substr($origFileName, strlen(PATH_TO_APP_ROOT));
        }
        $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem

        $sql = <<<EOT
INSERT INTO "$tableName" ("data", "structure", "origFile", "recLastUpdates", "recLocks", "lockedBy", "lockTime", "lastUpdate")
VALUES ("", "", "$origFileName", "[]", "[]", "", 0.0, $modifTime);
EOT;
        $stmt = $this->lzyDb->prepare($sql);
        if (!$stmt) {
            die("Error: unable to initialize table in lzyDB: '$tableName'");
        }
        $res = $stmt->execute();
        if ($res === false) {
            die("Error: unable to initialize table in lzyDB: '$tableName'");
        }

        $res = $this->importFromFile(true);
        if ($res === false) {
            die("Error: unable to populate table in lzyDB: '$tableName'");
        }
        $this->lowLevelWriteStructure();
    } // createNewTable



    private function loadFile()
    {
        if (!file_exists($this->dataFile)) {
            if (\Usility\PageFactory\isLocalhost()) {
                die("Error in datastorage loadFile(): file '$this->dataFile' not found.");
            } else {
                return false;
            }
        }
        $lines = file($this->dataFile);
        $rawData = '';
        foreach ($lines as $line) {
            if (strpos($line, '__END__') === 0) {
                break;
            }
            if ($line && ($line[0] !== '#') && ($line[0] !== "\n")) { // skip commented and empty lines
                $rawData .= $line;
            }
        }
        return $rawData;
    } // loadFile




    private function completeData($data)
    {
        if (!$data) {
            return [];
        }
        $data1 = [];
        foreach ($data as $recKey => $elem) {
            foreach (array_keys($this->structure['elements']) as $elemKey) {
                if (isset($data[$recKey][$elemKey])) {
                    $data1[$recKey][$elemKey] = $data[$recKey][$elemKey];
                } else {
                    $data1[$recKey][$elemKey] = '';
                }
            }
        }
        return $data1;
    } // completeData



    private function mylog($str)
    {
        \Usility\PageFactory\mylog($str);
    }


    private function findArrayElementByAttribute( $array, $key, $value) {
        $res = array_filter($array, function ($rec) use($key, $value) {
            if (isset($rec[$key])) {
                if (($value === null) || ($rec[$key] === $value)) {
                    return true;
                }
            }
            return false;
        });
        return $res;
    } // findArrayElementByAttribute

} // DataStorage

