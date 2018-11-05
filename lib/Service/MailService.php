<?php
declare(strict_types=1);


/**
 * Files_FullTextSearch_Mail - Parse your mails before index
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */


namespace OCA\Files_FullTextSearch_Mail\Service;


use Exception;
use OCP\Files\File;
use OCP\Files\Node;
use OCP\Files_FullTextSearch\Model\AFilesDocument;
use OCP\FullTextSearch\Model\IndexDocument;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;
use PhpMimeMailParser\Parser;
use Symfony\Component\EventDispatcher\GenericEvent;
use thiagoalessio\TesseractOCR\TesseractOCR;


/**
 * Class MailService
 *
 * @package OCA\Files_FullTextSearch_Mail\Service
 */
class MailService {


	/** @var ConfigService */
	private $configService;

	/** @var MiscService */
	private $miscService;


	/**
	 * MailService constructor.
	 *
	 * @param ConfigService $configService
	 * @param MiscService $miscService
	 */
	public function __construct(ConfigService $configService, MiscService $miscService) {
		$this->configService = $configService;
		$this->miscService = $miscService;
	}


	/**
	 * @param string $mimeType
	 * @param string $extension
	 *
	 * @return bool
	 */
	public function parsedMimeType(string $mimeType, string $extension): bool {
		$ocrMimes = [
			'image/png',
			'image/jpeg',
			'image/tiff',
			'image/vnd.djvu'
		];

		foreach ($ocrMimes as $mime) {
			if (strpos($mimeType, $mime) === 0) {
				return true;
			}
		}

		if ($mimeType === 'application/octet-stream') {
			return $this->parsedExtension($extension);
		}

		return false;
	}


	/**
	 * @param GenericEvent $e
	 */
	public function onFileIndexing(GenericEvent $e) {
//		if ($this->configService->getAppValue(ConfigService::MAILPARSE_ENABLED) !== '1') {
//			return;
//		}

		/** @var Node $file */
		$file = $e->getArgument('file');

		if (!$file instanceof File) {
			return;
		}

		/** @var AFilesDocument $document */
		$document = $e->getArgument('document');

		if ($file->getExtension() !== 'eml') {
			return;
		}

		$this->extractContent($document, $file);
	}


	/**
	 * @param GenericEvent $e
	 */
	public function onSearchRequest(GenericEvent $e) {
		/** @var ISearchRequest $request */
		$request = $e->getArgument('request');

		foreach ($request->getOptionArray('and:from', []) as $from) {
			$request->addWildcardFilter(['from' => '*' . strtolower($from) . '*']);
		}

		foreach ($request->getOptionArray('and:to', []) as $to) {
			$request->addWildcardFilter(['to' => '*' . strtolower($to) . '*']);
		}
	}


	/**
	 * @param GenericEvent $e
	 */
	public function onSearchResult(GenericEvent $e) {

		/** @var ISearchResult $result */
		$result = $e->getArgument('result');

//		$this->miscService->log('###' . json_encode($result));
	}


	/**
	 * @param AFilesDocument $document
	 * @param File $file
	 */
	private function extractContent(AFilesDocument &$document, File $file) {

		try {
			$Parser = new Parser();

			$Parser->setText($file->getContent());

			$to = array_map(
				function($item) {
					return $item['address'];
				}, $Parser->getAddresses('to')
			);

			$from = array_map(
				function($item) {
					return $item['address'];
				}, $Parser->getAddresses('from')
			);

			$subject = $Parser->getHeader('subject');

			$document->setInfoArray('to', $to);
			$document->setInfoArray('from', $from);
			$document->addPart('subject', $subject);
			$document->setContent(
				base64_encode($Parser->getMessageBody('text')), IndexDocument::ENCODED_BASE64
			);
		} catch (Exception $e) {
			return;
		}

	}


	/**
	 * @param string $extension
	 *
	 * @return bool
	 */
	private function parsedExtension(string $extension): bool {
		$ocrExtensions = [
//					'djvu'
		];

		if (in_array($extension, $ocrExtensions)) {
			return true;
		}

		return false;
	}


}
