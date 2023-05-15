import os
import signal
import sys
import time

# Keep files for 73 hours  (3 days + 1 hours)
MAX_FILE_AGE_SECONDS = (72 + 1) * 60 * 60

# Once done cleaning, sleep for 5 minutes
SLEEP_TIME_SECONDS = 5 * 60

# Handling shutdown signals
def signal_handler(sig, frame):
    sys.exit(0)

signal.signal(signal.SIGTERM, signal_handler)

def delete_old_files(directory, current_time):
    deletion_count = 0
    
    for filename in os.listdir(directory):
        filepath = os.path.join(directory, filename)
        
        if os.path.isfile(filepath):
            file_creation_time = os.path.getctime(filepath)
            
            if (current_time - file_creation_time) > (MAX_FILE_AGE_SECONDS):
                os.remove(filepath)
                deletion_count = deletion_count + 1
    
    return deletion_count

print("Deleting old files...")

start_time = time.time()

file_deleted_count = 0

for item in os.listdir("/data/"):
    item_path = os.path.join(folder_path, item)
    
    if os.path.isdir(item_path):
        file_deleted_count = file_deleted_count + delete_old_files(item_path, start_time)
    else:
        # Ignoring files
        continue

end_time = time.time()

print("Took {} second(s) to delete {} file(s)".format(round(end_time - start_time, 2), file_deleted_count))

time.sleep(SLEEP_TIME_SECONDS)
