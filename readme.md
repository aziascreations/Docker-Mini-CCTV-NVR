# Mini CCTV NRV
A mini docker stack that allows you to easily record, clean and serve CCTV recordings made over RSTP while using a
minimal amount of system resources.

## Preamble
This stack records the camera's streams as-is and doens't re-encode or compress it which can cause it to use more disk space.<br>
The highest I've got on my side is around 70 GiB per day for a 4K-ish cam with AAC audio.

It is highly recommended to put the web page behind a secure reverse-proxy that requires authentication for aditional security.

## Setup
Since the stack is a bit rough around the edges for simplicity''s sake you **will** need to setup a couple of things beforehand.

It should only take 2-3 minutes if you already have the RTSP URL on hand however.

### Cameras
Each recording container needs to be given a RSTP stream URL and a unique folder into which the recordings will go.

The URL must be given via the `NP_CCTV_URL` environment variable, and the output folder via a mounted volume that is
mounted as `/data` in the container.

Here is a simple example:
```yaml
  cctv_recorder_cam1:
    container_name: cctv-recorder-cam1
    build:
      context: .
      dockerfile: Dockerfile_recorder
    environment:
      - TZ=Europe/Brussels
      - "NP_CCTV_URL=rtsp://user:password@address:554/sub-path"
    volumes:
      - ./recordings/cam1:/data
    restart: unless-stopped
```

This example will use the `rtsp://user:password@address:554/sub-path` URL and will put its recordings in `./recordings/cam1`.

### Cleaner
The cleaner script named [cleaner.py](cleaner.py) only requires you to change 1 variable located near the top of the file.

The variable named `MAX_FILE_AGE_SECONDS` is used to indicate how long recordings should be kept for and is set to
73 hours by default.

It can also be changed as you wish without recreating the stack since the script is "reloaded" on each run.

### Web interface
The web interface only requires you to give it the list of all cams' IDs and a friendly name in the `$camsInfo` variable.

This variable is located at the top of the [htdocs/index.php](htdocs/index.php) file and should look like this:
```php
$camsInfo = [
	# Format: [camId, camName]
	["cam1", "Cam #1"],
	["cam2", "Cam #2"]
];
```

The cam's ID refers to a subfolder of `./recordings` into which this cam's recordings are found as `.mp4` or `.mkv` files.

## Startup
Once you have finished setting up the stack, you can simply run the following command:
```bash
docker-compose up --build -d
```

## Screenshots
### Home page
![alt text](screenshots/home.png)

### Camera's page with blurred preview
![alt text](screenshots/cam.png)


## License
This software, as well as the [Simplette CSS Stylesheet](https://github.com/aziascreations/Simplette-CSS-Stylesheet)
used for the web interface are both licensed under [Unlicense](LICENSE).
