   public function generate_bulk($ids, $request, $debug = false):bool{
        $images = [];
        /** @var Client $client */
        $clients = $this->em->getRepository('App:Client')->findBy(['id'=>$ids]); // ABOUT 25 clients
        $error = false;

        $message = '';
        $imageRequest = [];
        $clientMap = [];
        foreach($clients as $client){
            foreach($client->getReports() as $url): // About 3-8 urls
                $ScreenshotsCloudURL = \ScreenshotsCloud\ScreenshotsCloud::screenshotUrl([
                    "url" => $url,
                    "format" => "jpg",
                    "quality" => 90,
                    "cache_time" => 0,
                    "force" => true,
                    "width" => 900,
                    "browser" => 'chrome',
                    "wait_selector" => '.reportArea',
                    "clip_selector" => '.reportArea',
                    //"timeout" => 300000,
                    "viewport_height" => '5000',
                    "waitStrategy" => "networkIdle2",
                    "click_selector" => ".mode-control-button",
                    "viewport_width" => 3000
                   // "delay" => 30000 
                ], 'b8ca3237-7d48-4edd-a848-2c67cd7376ab',  'BZ1QPy90uGOlqufwZMP3OHSzL7JEXDE5oO1iLpqhaQBXZmRUFM');
                $response = $this->client->request('GET', $ScreenshotsCloudURL, [
                    'timeout' => 100
                ]);
                $imagesResponses[] = $response;
                $clientMap[spl_object_hash($response)] = $client->getId();
            endforeach;
        }
        
        $clientImages = [];
        foreach ($imagesResponses as $key => $response) {
            $clientId = $clientMap[spl_object_hash($response)];
            try{
                if($response->getStatusCode() == 200):
                    $content = $response->getContent(false);
                    if (!isset($clientImages[$clientId])) {
                        $clientImages[$clientId] = [];
                    }
                    $clientImages[$clientId][] = $content;
                else:

                endif;
            }catch (\Exception $e) { 
                $this->logger('Exception', $e);
            }
        }

        //Debug
        if($debug) return true;

        //Add log
        $this->setLog($client, $request, $message);
        
        foreach ($clientImages as $clientId => $images) {
            $imageInstance = new \Imagick();
            if (!empty($images)) {
                $client = $this->em->getRepository('App:Client')->findOneBy(['id' => $clientId]);
                foreach ($images as $key => $image) {
                    if(!$key){ //First image
                        $imageInstance->readimageblob($image);
                    }else{ //Other images
                        $temporaryImage = new \Imagick();
                        $temporaryImage->readimageblob($image);
                        $temporaryImage->cropImage(900, $temporaryImage->getImageHeight()-65, 0, 65); //remove header
                        $imageInstance->readimageblob($temporaryImage->getImageBlob());
                    }
                }
                /** @var Images $image */
                $imageEntity = new Images();
                $imageEntity->setDate();

                $imageInstance->resetIterator();
                $image = $imageInstance->appendImages(true); //TRUE - Append images vertically
                $image->setImageFormat("jpg");
                $fileName = $client->getSlug() . '-' . $imageEntity->getDate()->format('Y-m-d_H:i:s') . '.jpg';
                $path = $this->params->get('kernel.project_dir') . '/public_html/reports/' . $client->getSlug() . '/';
                if (!is_dir($path)) mkdir($path);
                file_put_contents($path . $fileName, $image);

                $imageEntity->setClient($client);
                $imageEntity->setRequest($request);
                $imageEntity->setPath('reports/' . $client->getSlug() . '/' . $fileName);
                $this->em->persist($imageEntity);
                $this->em->flush();
            }
        }
        return true;
    }
