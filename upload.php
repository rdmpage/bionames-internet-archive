<?php

require_once (dirname(__FILE__) . '/config.inc.php');
require_once (dirname(__FILE__) . '/lib.php');
require_once (dirname(__FILE__) . '/xmp.php');

//----------------------------------------------------------------------------------------


$tmpdir = dirname(__FILE__) . '/tmp';

// BioNames

$ids=array('d21c83a36e4791c638c876849f201174'); // sici

$ids=array('635ed28557050bd2897e1485af1bfe4b');

$ids=array('6cd569fe0d4361da7771ba177c5e4b11');


$force = false;
//$force = true;

$add_xmp = false;

foreach ($ids as $sici)
{
	echo "$sici...";
	
	// Have we done this already?	
	$pdf_url = 'https://archive.org/download/bionames-' . $sici . '/bionames-' . $sici . '.pdf';
	if (head($pdf_url) && !$force)
	{
		echo " PDF exists (HEAD returns 200)\n";
	}
	else
	{
		// OK, need to do this
		
		// Get metadata
		$json = get('http://bionames.org/api/id/' . $sici);

		if ($json != '')
		{
			$reference = json_decode($json);
		
			//print_r($reference);
						
				
			// Fetch PDF from BioNames	
			$sha1 = '';
			
			if (isset($reference->file))
			{
				if (isset($reference->file->sha1))
				{
					$sha1 = $reference->file->sha1;
				}
			}

			if ($sha1 != '')
			{
				// fetch PDF
				$cache_dir =  dirname(__FILE__) . "/cache/";
				$article_pdf_filename = $cache_dir . '/' . $sha1 . '.pdf';
				
				if (file_exists($article_pdf_filename) && !$force)
				{
					echo "Have PDF $article_pdf_filename\n";
				}
				else
				{				
					$url = 'http://bionames.org/bionames-archive/pdfstore?sha1=' . $sha1;
					$command = "curl --location " . $url . " > " . $article_pdf_filename;
					echo $command . "\n";
					system ($command);
				}
								
				// Have PDF, now do something with it...
				if (1)
				{
					$identifier = 'bionames-' . $sici;
		
					// upload to IA
					$headers = array();
			
					$headers[] = '"x-archive-auto-make-bucket:1"';
					$headers[] = '"x-archive-ignore-preexisting-bucket:1"';
					$headers[] = '"x-archive-interactive-priority:1"';
			
					// collection
					//$headers[] = '"x-archive-meta01-collection:bionames"';

					// metadata
					$headers[] = '"x-archive-meta-sponsor:BioNames"';
					$headers[] = '"x-archive-meta-mediatype:texts"'; 
			
					
					if (isset($reference->title))
					{
						$headers[] = '"x-archive-meta-title:' . addcslashes($reference->title, '"') . '"';
					}
					if (isset($reference->journal))
					{
						if (isset($reference->journal->name))
						{
							$headers[] = '"x-archive-meta-journaltitle:' . addcslashes($reference->journal->name, '"') . '"';
						}
						if (isset($reference->journal->volume))
						{
							$headers[] = '"x-archive-meta-volume:' . addcslashes($reference->journal->volume, '"') . '"';
						}
						if (isset($reference->journal->pages))
						{
							$headers[] = '"x-archive-meta-pages:' . str_replace('--', '-', $reference->journal->pages) . '"';
						}
					}
					if (isset($reference->year))
					{
						$headers[] = '"x-archive-meta-year:' . addcslashes($reference->year, '"') . '"';
						$headers[] = '"x-archive-meta-date:' . addcslashes($reference->year, '"') . '"';
					}

					if (isset($reference->author))
					{
						for ($i = 0; $i < count($reference->author); $i++)
						{
							$headers[] = '"x-archive-meta' . str_pad(($i+1), 2, 0, STR_PAD_LEFT) . '-creator:' . addcslashes($reference->author[$i]->name, '"') . '"';
						}
					}
					
					if (isset($reference->identifier))
					{
						foreach ($reference->identifier as $identifier)
						{
							switch ($identifier->type)
							{
								case 'doi':
									$headers[] = '"x-archive-meta-external-identifier:' . 'doi:' . $identifier->id . '"';
									break;
									
								default:
									break;
							}
						}
					}
												
					// licensing
					//$headers[] = '"x-archive-meta-licenseurl:http://creativecommons.org/licenses/by-nc/3.0/"';

					// authorisation
					$headers[] = '"authorization: LOW ' . $config['s3_access_key']. ':' . $config['s3_secret_key'] . '"';

					$headers[] = '"x-archive-meta-identifier:' . $identifier . '"';
			
					$url = 'http://s3.us.archive.org/' . $identifier . '/' . $identifier . '.pdf';
			
					print_r($headers);
					echo "$url\n";
			
					$command = 'curl --location';
					$command .= ' --header ' . join(' --header ', $headers);
					$command .= ' --upload-file ' . $article_pdf_filename;
					$command .= ' http://s3.us.archive.org/' . $identifier . '/' . $identifier . '.pdf';

					echo $command . "\n";

					system ($command);
			
				}	
			}
		}		
	}
}




?>