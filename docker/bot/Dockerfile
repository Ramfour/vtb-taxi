FROM python:3.11-slim

WORKDIR /app

COPY bot/aii-master/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY bot/aii-master/ .

CMD ["python", "bot.py"]
