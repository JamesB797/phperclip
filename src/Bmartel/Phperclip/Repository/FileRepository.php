<?php namespace Bmartel\Phperclip\Repository;

use Bmartel\Phperclip\Contracts\FileRepository as FileRepositoryContract;
use Bmartel\Phperclip\Model\Clippable;
use Bmartel\Phperclip\Model\File as FileModel;

class FileRepository implements FileRepositoryContract {

	/**
	 * Creates a new File object in the database.
	 *
	 * @param $attributes
	 * @return FileModel
	 */
	public function create($attributes) {

		return FileModel::create($attributes);
	}

	/**
	 * Gets a File object by it's id.
	 *
	 * @param int $id
	 * @return null|FileModel
	 */
	public function getById($id) {

		return FileModel::find($id);
	}

	/**
	 * @param $slot
	 * @param Clippable $clippable
	 * @return null|FileModel
	 */
	public function getBySlot($slot, Clippable $clippable = null) {

		if ($clippable) {
			$query = FileModel::forClippable(get_class($clippable), $clippable->getKey());
		} else {
			$query = FileModel::unattached();
		}

		return $query->inSlot($slot)->first();

	}
} 