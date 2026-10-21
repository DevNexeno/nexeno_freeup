<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\NexenoFreeUp\Service\Duplicates;

use OCP\Files\IRootFolder;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class SkeletonFilter {
	/**
	 * @var array<string, string>|null
	 */
	private ?array $skeletonHashes = null;

	// Text files are compared normalised, so a changed final newline or line ending still matches.
	private const SKELETON_TEXT_EXTENSIONS = ['md', 'txt'];
	private const SKELETON_TEXT_MAX_BYTES = 65536;

	/** @var array<string, true>|null */
	private ?array $skeletonTextHashes = null;

	public function __construct(
		private IConfig $config,
		private IRootFolder $rootFolder,
		private LoggerInterface $logger,
	) {
	}

	private function normalisedTextHash(string $content): string {
		return hash('sha256', rtrim(str_replace("\r\n", "\n", $content)));
	}

	/**
	 * @return array<string, true>
	 */
	private function getSkeletonTextHashes(): array {
		if ($this->skeletonTextHashes !== null) {
			return $this->skeletonTextHashes;
		}
		$this->skeletonTextHashes = [];
		$skeletonDir = $this->config->getSystemValueString('skeletondirectory', \OC::$SERVERROOT . '/core/skeleton');
		if ($skeletonDir === '' || !is_dir($skeletonDir)) {
			return $this->skeletonTextHashes;
		}
		try {
			$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($skeletonDir, \FilesystemIterator::SKIP_DOTS));
			foreach ($it as $file) {
				if ($file->isFile() && $file->isReadable()
					&& in_array(strtolower($file->getExtension()), self::SKELETON_TEXT_EXTENSIONS, true)
					&& $file->getSize() <= self::SKELETON_TEXT_MAX_BYTES) {
					$this->skeletonTextHashes[$this->normalisedTextHash((string)file_get_contents($file->getPathname()))] = true;
				}
			}
		} catch (\Throwable $e) {
			$this->logger->warning('NexenoFreeUp: Error reading skeleton text files: ' . $e->getMessage());
		}
		return $this->skeletonTextHashes;
	}

	public function isSkeletonText(array $file, int $size, string $dataDir): bool {
		if ($size > self::SKELETON_TEXT_MAX_BYTES
			|| !in_array(strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION)), self::SKELETON_TEXT_EXTENSIONS, true)) {
			return false;
		}
		$skeleton = $this->getSkeletonTextHashes();
		if ($skeleton === []) {
			return false;
		}
		$content = false;
		$localPath = $dataDir . '/' . $file['userId'] . '/' . ($file['rawPath'] ?? $file['path']);
		if (is_readable($localPath)) {
			$content = file_get_contents($localPath);
		} else {
			try {
				$node = $this->rootFolder->getUserFolder((string)$file['userId'])->get((string)$file['path']);
				$content = $node instanceof \OCP\Files\File ? $node->getContent() : false;
			} catch (\Throwable $e) {
				$content = false;
			}
		}
		return is_string($content) && isset($skeleton[$this->normalisedTextHash($content)]);
	}

	/**
	 * @return array<string, string> Map of [sha256_hash => filename]
	 */
	public function getSkeletonFileHashes(): array {
		if ($this->skeletonHashes !== null) {
			return $this->skeletonHashes;
		}

		$this->skeletonHashes = [];
		$skeletonDir = $this->config->getSystemValueString('skeletondirectory', \OC::$SERVERROOT . '/core/skeleton');

		if (!empty($skeletonDir) && is_dir($skeletonDir)) {
			try {
				$it = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($skeletonDir, \FilesystemIterator::SKIP_DOTS)
				);
				foreach ($it as $file) {
					if ($file->isFile() && $file->isReadable()) {
						$hash = hash_file('sha256', $file->getPathname());
						if ($hash) {
							$this->skeletonHashes[$hash] = $file->getFilename();
						}
					}
				}
			} catch (\Throwable $e) {
				$this->logger->warning('NexenoFreeUp: Error reading skeleton directory: ' . $e->getMessage());
			}
		}

		return $this->skeletonHashes;
	}
}
