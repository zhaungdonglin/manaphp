<?php

namespace ManaPHP\Http\Request;

use ManaPHP\Component;
use ManaPHP\Http\Request\File\Exception as FileException;

/**
 * Class ManaPHP\Http\Request\File
 *
 * @package request
 */
class File extends Component implements FileInterface
{
    /**
     * @var array
     */
    protected $_file;

    /**
     * \ManaPHP\Http\Request\File constructor
     *
     * @param array $file
     */
    public function __construct($file)
    {
        $this->_file = $file;
    }

    /**
     * Returns the file size of the uploaded file
     *
     * @return int
     */
    public function getSize()
    {
        return $this->_file['size'];
    }

    /**
     * Returns the real name of the uploaded file
     *
     * @return string
     */
    public function getName()
    {
        return $this->_file['name'];
    }

    /**
     * Returns the temporary name of the uploaded file
     *
     * @return string
     */
    public function getTempName()
    {
        return $this->_file['tmp_name'];
    }

    /**
     * @param bool $real
     *
     * @return string
     */
    public function getType($real = true)
    {
        if ($real) {
            return mime_content_type($this->_file['tmp_name']) ?: '';
        } else {
            return $this->_file['type'];
        }
    }

    /**
     * Returns the error code
     *
     * @return string
     */
    public function getError()
    {
        return $this->_file['error'];
    }

    /**
     * Returns the file key
     *
     * @return string
     */
    public function getKey()
    {
        return $this->_file['key'];
    }

    /**
     * Checks whether the file has been uploaded via Post.
     *
     * @return bool
     */
    public function isUploadedFile()
    {
        return is_uploaded_file($this->_file['tmp_name']);
    }

    /**
     * Moves the temporary file to a destination within the application
     *
     * @param string $dst
     * @param string $allowedExtensions
     * @param bool   $overwrite
     *
     * @throws \ManaPHP\Http\Request\File\Exception
     */
    public function moveTo($dst, $allowedExtensions = 'jpg,jpeg,png,gif,doc,xls,pdf,zip', $overwrite = false)
    {
        if ($allowedExtensions !== '*') {
            $extension = pathinfo($dst, PATHINFO_EXTENSION);
            if (!$extension || preg_match("#\b$extension\b#", $allowedExtensions) !== 1) {
                throw new FileException(['`:extension` file type is not allowed upload', 'extension' => $extension]);
            }
        }

        if ($this->_file['error'] !== UPLOAD_ERR_OK) {
            throw new FileException(['error code of upload file is not UPLOAD_ERR_OK: :error', 'error' => $this->_file['error']]);
        }

        if ($this->filesystem->fileExists($dst)) {
            if ($overwrite) {
                $this->filesystem->fileDelete($dst);
            } else {
                throw new FileException(['`:file` file already exists', 'file' => $dst]);
            }
        }

        $this->filesystem->dirCreate(dirname($dst));

        if (PHP_SAPI === 'cli') {
            $this->filesystem->fileMove($this->_file['tmp_name'], $this->alias->resolve($dst));
        } else {
            if (!move_uploaded_file($this->_file['tmp_name'], $this->alias->resolve($dst))) {
                throw new FileException(['move_uploaded_file to `:dst` failed: :last_error_message', 'dst' => $dst]);
            }
        }

        if (!chmod($this->alias->resolve($dst), 0644)) {
            throw new FileException(['chmod `:dst` destination failed: :last_error_message', 'dst' => $dst]);
        }
    }

    /**
     * Returns the file extension
     *
     * @return string
     */
    public function getExtension()
    {
        $name = $this->_file['name'];
        return ($extension = pathinfo($name, PATHINFO_EXTENSION)) === $name ? '' : $extension;
    }

    /**
     * @return void
     */
    public function delete()
    {
        @unlink($this->_file['tmp_name']);
    }

    public function jsonSerialize()
    {
        return $this->_file;
    }
}