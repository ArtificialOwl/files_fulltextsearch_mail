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
use OC\Files\View;
use OCP\Files\File;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Files_FullTextSearch\Model\AFilesDocument;
use OCP\FullTextSearch\Model\IndexDocument;
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
		/** @var Node $file */
		$file = $e->getArgument('file');

		if (!$file instanceof File) {
			return;
		}

		/** @var \OCP\Files_FullTextSearch\Model\AFilesDocument $document */
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
	}


	/**
	 * @param AFilesDocument $document
	 * @param File $file
	 */
	private function extractContent(AFilesDocument &$document, File $file) {

		try {
//			if ($this->configService->getAppValue(ConfigService::TESSERACT_ENABLED) !== '1') {
//				return;
//			}

//			$extension = pathinfo($document->getPath(), PATHINFO_EXTENSION);
//			if (!$this->parsedMimeType($document->getMimetype(), $extension)) {
//				return;
//			}

			// TODO: How to set options so that the index can be reset if admin settings are changed
			//	$this->configService->setDocumentIndexOption($document, ConfigService::FILES_OCR);

			$Parser = new Parser();

			$Parser->setText($file->getContent());


// Once we've indicated where to find the mail, we can parse out the data
			$to = $Parser->getHeader(
				'to'
			);             // "test" <test@example.com>, "test2" <test2@example.com>
			$addressesTo = $Parser->getAddresses(
				'to'
			); //Return an array : [["display"=>"test", "address"=>"test@example.com", false],["display"=>"test2", "address"=>"test2@example.com", false]]

			$from = $Parser->getHeader('from');             // "test" <test@example.com>
			$addressesFrom = $Parser->getAddresses(
				'from'
			); //Return an array : [["display"=>"test", "address"=>"test@example.com", "is_group"=>false]]

			$subject = $Parser->getHeader('subject');

			$text = $Parser->getMessageBody('text');


			$content = $text;
		} catch (Exception $e) {
			return;
		}

		$document->setContent(base64_encode($content), IndexDocument::ENCODED_BASE64);
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
