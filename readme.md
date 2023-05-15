# Mini CCTV NRV
A mini docker stack that allows you to easily record, clean and serve CCTV recordings made over RSTP while using a
minimal amount of system resources.

This stack is mainly intended to be used as a backup when other and more complete solutions crash or need to be shutdown.

## Preamble
This stack records the camera's streams as-is and doens't re-encode or compress it which uses more disk space.<br>
See "[Usage statistics example](#usage-statistics-example)" for an example.

If served out of your LAN, the web server should be behind a secure reverse-proxy that requires authentication.

## Setup
All of the setup is done through environment variables in the [docker-compose.yml](docker-compose.yml) file.

It should only take 2-3 minutes if you already have the RTSP URL on hand.<br>
If you don't have them, you should see your camera's user manual and test the URLs with [VLC](https://www.videolan.org/vlc/).

### Cameras
Each recording container needs to be given a RSTP stream URL and a unique folder into which the recordings will go.

The URL must be given via the `NP_CCTV_URL` environment variable, and the output folder via a mounted volume that is
mounted as `/data` in the container.

#### Example:
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
The cleaner script named [cleaner.py](cleaner.py) only requires you to set 1 environment variable named `NP_MAX_FILE_AGE_HOURS`
to the max amount of hours any recording should be kept.

If not set, the script will simply clean any recordings older than 72 hours.

#### Example
```yaml
  cctv_cleaner:
    container_name: cctv-cleaner
    build:
      context: .
      dockerfile: Dockerfile_cleaner
    environment:
      - TZ=Europe/Brussels
      - "NP_MAX_FILE_AGE_HOURS=72"
    volumes:
      - ./recordings:/data
      - ./cleaner.py:/app/app.py:ro
    restart: unless-stopped
```

### Web interface
The web interface provides more customization options, but at its core, it only requires the camera's environment variables to be set.

Each camera requires one of the following environment variable:<br>
`NP_CAM_<camId> = <Camera's name>`

Here is an example for `cam1` if named as `Camera #1`:<br>
`NP_CAM_cam1 = Camera #1`

#### Other variables
| Variable    | Description                |
| ----------- | -------------------------- |
| `NP_TITLE`  | Page's title               |
| `NP_FOOTER` | Page's footer HTML content |

#### Example:
```yaml
  cctv_web:
    container_name: cctv-web
    image: php:apache
    ports:
      - 26880:80
    environment:
      - TZ=Europe/Brussels
      - "NP_CAM_cam1=Camera #1"
      - "NP_CAM_cam2=Camera #2"
      - "NP_TITLE=NibblePoker's Mini CCTV NVR"
      - "NP_FOOTER=Made by <i>BOZET Herwin</i>"
    volumes:
      - ./htdocs:/var/www/html  # Cannot be ":ro" since the recordings are mounted into it.
      - ./apache2.conf:/etc/apache2/apache2.conf:ro
      - ./recordings:/var/www/html/data:ro
    restart: unless-stopped
```

## Startup
Once you have finished setting up the stack, you can simply run the following command:
```bash
docker-compose up --build -d
```

## Screenshots
![alt text](screenshots/home.png)
![alt text](screenshots/cam.png)

## Usage statistics example
* NanoPi R4S 4GB
  * Uses 0.008 kWh / 8 Watts with other containers and USB HDD & USB SSD
* 4 IP Cameras
  * All H.256 4k RTSP TCP streams
  * Around 220 GB of data per day
    * Around 20.4 Mbit/s or 2.6 MB/s
* Less than 200MB of RAM usage
  * ~32 MB per recorder
  * 4 MB for cleaner
  * 4 MB for web server
* Uses ~10% of CPU on average over 6 cores
  * Average of 15% per recorder
  * Average of 1-5% on cleaner and web server

## License
This software, as well as the [Simplette CSS Stylesheet](https://github.com/aziascreations/Simplette-CSS-Stylesheet)
used for the web interface are both licensed under [Unlicense](LICENSE).
