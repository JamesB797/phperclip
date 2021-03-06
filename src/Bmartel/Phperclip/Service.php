<?php namespace Bmartel\Phperclip;

use Bmartel\Phperclip\Model\File as FileModel;
use Bmartel\Phperclip\Model\Clippable;
use Bmartel\Phperclip\Processes\ProcessManager;
use Bmartel\Phperclip\Contracts\FileRepository;
use Symfony\Component\HttpFoundation\File\File;

class Service {

	/**
	 * @var \Bmartel\Phperclip\Contracts\FileRepository
	 */
	protected $fileRepository;

	/**
	 * @var \Bmartel\Phperclip\Storage\Driver[]
	 */
	protected $storageDrivers;

	/**
	 * @var \Bmartel\Phperclip\Storage\Driver
	 */
	protected $currentDriver;

	/**
	 * @var \Bmartel\Phperclip\Processes\ProcessManager
	 */
	protected $processManager;

	/**
	 * @param FileRepository $fileRepository
	 * @param ProcessManager $processManager
	 * @param \Bmartel\Phperclip\Storage\Driver[] $storageDrivers
	 * @throws \Exception
	 */
	public function __construct(
		FileRepository $fileRepository,
		ProcessManager $processManager,
		array $storageDrivers
	) {

		$this->fileRepository = $fileRepository;
		$this->processManager = $processManager;

		if (empty($storageDrivers)) {
			throw new \Exception('You must configure at least one file storage driver for Phperclip to use.');
		}

		$this->storageDrivers = $storageDrivers;
		$this->currentDriver = current($storageDrivers);

	}

	//
	// General Methods
	//

	/**
	 * Select which driver Phperclip uses by default.
	 *
	 * @param $abstract
	 */
	public function useDriver($abstract) {

		$this->currentDriver = $this->storageDrivers[$abstract];
	}

	/**
	 * Simply retrieves a file by id.
	 *
	 * @param int $id
	 * @return Model\File
	 */
	public function getById($id) {

		return $this->fileRepository->getById($id);
	}

	/**
	 * @param $slot
	 * @param Clippable $clippable
	 * @return Model\File
	 */
	public function getBySlot($slot, Clippable $clippable = null) {

		return $this->fileRepository->getBySlot($slot, $clippable);
	}

	/**
	 * @param FileModel $fileModel
	 * @param array $options
	 * @return string
	 */
	public function getPublicUri(FileModel $fileModel, array $options = []) {

		if (!$this->getDriver()->has($fileModel, $options)) {
			$tempOriginal = $this->getDriver()->tempOriginal($fileModel);
			$this->saveFile($tempOriginal, $fileModel, $options);
		}

		return $this->getDriver()->getPublicUri($fileModel, $options);
	}

	/**
	 * Get all the files by mimetype or slot, attached or unattached to a model
	 *
	 * @param null|Clippable $clippable
	 * @param null|string|array $mimeTypes
	 * @param null|string|int|array $slot
	 * @return \Illuminate\Database\Eloquent\Collection
	 */
	public function getFilesFor(Clippable $clippable = null, $mimeTypes = null, $slot = null) {

		$query = ($clippable) ? $clippable->clippedFiles() : FileModel::query();

		// Filter by slot(s)
		if($slot) {

			$slot = is_array($slot) ? $slot : [$slot];

			$query->whereIn('slot', $slot);
		}

		// Filter by file type(s)
		if ($mimeTypes) {

			$mimeTypes = is_array($mimeTypes) ? $mimeTypes : [$mimeTypes];

			$query->whereIn('mime_type', $mimeTypes);
		}

		return $query->get();
	}


	/**
	 * Returns a file URI based on the id of the original.
	 *
	 * @param $id
	 * @return string
	 */
	public function getPublicUriById($id) {

		return $this->getPublicUri($this->getById($id));
	}

	/**
	 * Returns a File URI based on the slot and clippable
	 *
	 * @param $slot
	 * @param Clippable $clippable
	 * @return null|string
	 */
	public function getPublicUriBySlot($slot, Clippable $clippable = null) {

		if ($file = $this->getBySlot($slot, $clippable)) {
			return $this->getPublicUri($file);
		}

		return null;
	}

	/**
	 * Saves a new file from a file found on the server's filesystem.
	 *
	 * @param File $file
	 * @param Clippable $clippable
	 * @param array $options
	 * @return null|FileModel
	 */
	public function saveFromFile(File $file, Clippable $clippable = null, array $options = []) {

		// Determine if there are any registered processors which know of this file type
		// and the current action scope.

		// Run any pre-save file processing, such as validation
		$file = $this->processManager->dispatch($file, 'onBeforeSave', $options);

		if (($file instanceof File) === false) {
			return null;
		}

		// Create the original file record
		$newFile = $this->createFileRecord($file, $options);
		$this->saveOriginalFile($file, $newFile);

		// Optionally attach the file to a model
		if ($clippable) {
			$clippable->clippedFiles()->save($newFile);
		}

		// Save a modified copy of the original file
		$this->saveFile($this->getDriver()->tempOriginal($newFile), $newFile, $options);

		return $newFile;
	}

	/**
	 * Saves a new local file from a file available via any of the standard PHP supported schemes.
	 *
	 * @param $uri
	 * @param Clippable $clippable
	 * @param array $options
	 * @return null|FileModel
	 */
	public function saveFromUri($uri, Clippable $clippable = null, array $options = []) {

		// Download the file.
		// Use sys_get_temp_dir so that systems-level configs can apply.
		$tempFilePath = tempnam(sys_get_temp_dir(), null);

		$fileContent = @file_get_contents($uri);

		file_put_contents($tempFilePath, $fileContent);

		return $this->saveFromFile(new File($tempFilePath), $clippable, $options);
	}

	/**
	 * Delete the file
	 *
	 * @param FileModel $fileModel
	 * @param array $options
	 */
	public function delete(FileModel $fileModel, array $options = []) {

		// Determine if there are any registered processors which know of this file type
		// and the current action scope.
		$fileModel = $this->processManager->dispatch($fileModel, 'onDelete', $options);

		if (($fileModel instanceof FileModel) === false) {
			return null;
		}

		// Perform the delete on the actual file
		$this->getDriver()->delete($fileModel, $options);

		// If this is the original file also remove it from the database.
		if (!array_key_exists($this->getDriver()->getModificationKey(), $options)) {
			$fileModel->forceDelete();
		}
	}

	/**
	 * @param $id
	 * @param array $options
	 */
	public function deleteById($id, array $options = []) {

		if ($file = $this->getById($id)) {
			$this->delete($file, $options);
		}
	}

	public function deleteBySlot($slot, Clippable $clippable = null) {

		if ($file = $this->getBySlot($slot, $clippable)) {
			$this->delete($file);
		}
	}

	//
	// Slot Methods
	//

	/**
	 * Perform batch operations on files.
	 *
	 * @param array $operations
	 * @param array $files
	 * @param Clippable $clippable
	 */
	public function batch(array $operations, array $files = null, Clippable $clippable = null) {

		// Perform any operations first so that files can move out of the way for new ones.
		foreach ($operations as $slot => $operation) {

			// Do deletes first.
			if (empty($operation)) {
				$this->deleteBySlot($slot, $clippable);
			} // Then move/swaps.
			elseif (is_int($operation)) {
				$this->moveToSlot($this->getById($operation), $slot);
			} // Then remote files.
			elseif (filter_input($operation, FILTER_VALIDATE_URL)) {

				try {
					$this->saveFromUri($operation, $clippable, ['attributes' => ['slot' => $slot]]);
				} catch (\Exception $ex) {
					// Displace whatever is in the slot.
					$this->moveToSlot($this->getBySlot($slot), null);
					$this->saveFromUri($operation, $clippable, ['attributes' => ['slot' => $slot]]);
				}

			}

		}

		// Handle file uploads.
		foreach ($files as $slot => $file) {
			try {
				$this->saveFromFile($file, $clippable, ['attributes' => ['slot' => $slot]]);
			} catch (\Exception $ex) {
				// Displace whatever is in the slot.
				$this->moveToSlot($this->getBySlot($slot), null);
				$this->saveFromFile($file, $clippable, ['attributes' => ['slot' => $slot]]);
			}
		}

	}

	/**
	 * Move a file from one logical slot to another.
	 *
	 * @param FileModel $fileModel
	 * @param $slot
	 * @return null
	 */
	public function moveToSlot(FileModel $fileModel, $slot) {

		// Determine if there are any registered processors which know of this file type
		// and the current action scope.
		$fileModel = $this->processManager->dispatch($fileModel, 'onMove');

		if (($fileModel instanceof FileModel) === false) {
			return null;
		}

		try {
			// Assign the new slot to our file.
			$fileModel->slot = $slot;
			$fileModel->save();
		} // Something is already in our slot.
		catch (\Exception $ex) {

			// Move the previous file out temporarily, we'll perform a swap.
			$previousSlotFile = $this->getBySlot($slot, $fileModel->clippable);
			$previousSlotFile->slot = null;
			$previousSlotFile->save();

			// Save the slot our file is in.
			$previousSlot = $fileModel->slot;
			// NOW save!
			$fileModel->slot = $slot;
			$fileModel->save();

			// If our file had a non-null slot, move the previous occupant of the target slot into it.
			if ($previousSlot !== null) {
				$previousSlotFile->slot = $previousSlot;
				$previousSlotFile->save;
			}

		}

	}

	//
	// Utility Methods
	//

	/**
	 * Gets the current or specified driver.
	 *
	 * @param null $abstract
	 * @return \Bmartel\Phperclip\Contracts\Driver
	 */
	protected function getDriver($abstract = null) {

		return $abstract ? $this->storageDrivers[$abstract] : $this->currentDriver;
	}

	/**
	 * Create the database entry for a file.
	 *
	 * @param File $file
	 * @param array $options
	 * @return FileModel
	 */
	protected function createFileRecord(File $file, array $options = []) {

		// Obtain file metadata and save the record to the database.

		$attributes = [
			'mime_type' => $file->getMimeType()
		];

		if (array_key_exists('attributes', $options) && is_array($options['attributes'])) {
			$attributes = array_merge($attributes, $options['attributes']);
		}

		return $this->fileRepository->create($attributes);
	}

	/**
	 * Saves an original unmodified version of the file.
	 *
	 * @param File $file
	 * @param FileModel $fileModel
	 */
	protected function saveOriginalFile(File $file, FileModel $fileModel) {
		$this->getDriver()->saveFile($file, $fileModel);
	}

	/**
	 * Pass a file save into the current Driver.
	 *
	 * @param File $file
	 * @param FileModel $fileModel
	 * @param array $options
	 */
	protected function saveFile(File $file, FileModel $fileModel, array $options = []) {

		// Run any file manipulation processors
		$file = $this->processManager->dispatch($file, 'onSave', $options);

		if (($file instanceof File) === false) {
			// If something fails inside the processors, clean up the file instances
			$this->delete($file, $options);
			return null;
		}

		$this->getDriver()->saveFile($file, $fileModel, $options);
	}
}