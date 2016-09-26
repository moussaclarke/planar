<?php
namespace MoussaClarke;

use \SebastianBergmann\Diff\Differ;

class Planar
{
    protected $data;
    protected $datafolder = null;
    protected $collectionname;
    protected $dbfile;
    protected $persists = true;
    protected $schema   = [];

    public function __construct($datafolder = null)
    {
        // if $datafolder not set, check the class property in case over-ridden
        $datafolder = $datafolder ? $datafolder : $this->datafolder;

        // if still not set, throw an exception
        if (!$datafolder) {
            throw new \Exception('Planar datafolder not set.');
        }

        // if the folder doesn't exist yet, let's make it
        if (!file_exists($datafolder)) {
            mkdir($datafolder);
        }
        // get the short name via reflection as we are in a namespace
        $reflect              = new \ReflectionClass($this);
        $this->collectionname = $reflect->getShortName();
        $this->backupfolder   = $datafolder . '/backups';
        $this->dbfile         = $datafolder . '/' . $this->collectionname . '.json';
        if (file_exists($this->dbfile)) {
            $this->data = json_decode(file_get_contents($this->dbfile), true);
        } else {
            $this->data = array();
            $this->save();
        }

    }

    public function getSchema()
    {
        // return the schema, which might just be a blank array
        return $this->schema;
    }

    public function find($key, $value)
    {
        // return an array of documents where property named $key has a particular value
        $found = array();
        foreach ($this->data as $item) {
            if ($item[$key] == $value) {
                $found[] = $item;
            }
        }
        return empty($found) ? false : $found;
    }

    public function first($key, $value)
    {
        // return the first document where property named $key has a particular value
        $found = false;
        foreach ($this->data as $item) {
            if ($item[$key] == $value) {
                $found = $item;
                break;
            }
        }
        return $found;
    }

    public function all($sortby = null)
    {
        // returns the whole collection, sorted
        $data = $this->data;

        if ($sortby) {
            uasort($data, function ($a, $b) use ($sortby) {
                return $a[$sortby] > $b[$sortby];
            });
        }
        return $data;
    }

    public function search($value)
    {
        // return an array of documents where any property contains $value
        // case insensitive
        $recursiveFind = function ($needle, $haystack) use (&$recursiveFind) {
            if (is_array($haystack)) {
                foreach ($haystack as $key => $itemvalue) {
                    if ($recursiveFind($needle, $itemvalue)) {
                        return true;
                        break;
                    };
                    return false;
                }
            } elseif (strpos(strtolower($haystack), strtolower($needle)) !== false) {
                return true;
            } else {
                return false;
            }
        };

        $found = array();
        foreach ($this->data as $item) {
            foreach ($item as $key => $itemvalue) {
                if ($recursiveFind($value, $item[$key])) {
                    $found[] = $item;
                    break;
                }
            }
        }
        return empty($found) ? false : $found;
    }

    public function set($id, array $properties)
    {
        // replaces a document
        $oldversion = $this->first('id', $id);
        if (!$oldversion) {
            return false;
        }
        $properties['id']       = $id;
        $properties['modified'] = time();
        $properties['created']  = $oldversion['created'];
        $this->data[$id]        = $properties;
        $this->save();
    }

    public function add(array $properties)
    {
        // adds a document
        $id                    = uniqid('');
        $properties['id']      = $id;
        $properties['created'] = time();
        $this->data[$id]       = $properties;
        $this->save();
        return $id;
    }

    public function delete($id)
    {
        // deletes a document
        if ($this->first('id', $id)) {
            unset($this->data[$id]);
            $this->save();
            return true;
        } else {
            return false;
        }
    }

    protected function save()
    {
        $this->preSaveTasks();
        $jsondata = json_encode($this->data, JSON_PRETTY_PRINT);
        file_put_contents($this->dbfile, $jsondata);
    }

    protected function preSaveTasks()
    {
        if ($this->persists) {
            $this->backup(); // backup persistent models
        } else {
            $this->garbage(); // garbage collect non-persistent models
        }
    }

    protected function garbage()
    {
        // clear out non-persistent models, once a day
        $allDocuments = $this->all();
        $now          = time();
        foreach ($allDocuments as $document) {
            if ($now - $document['created'] > 86400) {
                $this->delete($document['id']);
            }
        }
    }

    protected function backup()
    {
        if (!file_exists($this->backupfolder)) {
            mkdir($this->backupfolder);
        }
        $olddata = file_get_contents($this->dbfile);
        $newdata = json_encode($this->data, JSON_PRETTY_PRINT);
        //generate the diff
        $datestring = date("YmdHis");
        $differ     = new Differ;
        $result     = $differ->diff($olddata, $newdata);
        $backupfile = $this->backupfolder . '/' . $this->collectionname . '_' . $datestring . '.diff';
        file_put_contents($backupfile, $result);
    }

    protected function overwrite(array $collection)
    {
        //overwrites all data without validating - destructive!
        $this->data = $collection;
        $this->save();
    }

}
