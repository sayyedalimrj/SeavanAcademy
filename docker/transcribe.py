import json, os, sys, tempfile
from faster_whisper import WhisperModel
source, target = sys.argv[1:3]
model = WhisperModel("small", device="cpu", compute_type="int8", cpu_threads=2)
segments, info = model.transcribe(source, beam_size=3, vad_filter=True)
data = [{"start": round(s.start, 2), "end": round(s.end, 2), "text": s.text.strip()} for s in segments]
fd, tmp = tempfile.mkstemp(dir=os.path.dirname(target))
try:
    with os.fdopen(fd, "w", encoding="utf-8") as out:
        json.dump(data, out, ensure_ascii=False)
    os.replace(tmp, target)
finally:
    if os.path.exists(tmp):
        os.unlink(tmp)
