import logging
from urllib.parse import quote

import requests

from config import YANDEX_GEOCODER_API_KEY


logger = logging.getLogger(__name__)


def validate_address(address: str):
    """
    Проверяет адрес через Yandex Geocoder.
    Возвращает (is_valid, corrected_address).
    """
    if not YANDEX_GEOCODER_API_KEY or YANDEX_GEOCODER_API_KEY == "ВАШ_API_КЛЮЧ_YANDEX":
        logger.warning("Yandex Geocoder API key не задан, проверка адреса отключена")
        return True, address.strip()

    try:
        encoded_address = quote(address)
        url = (
            "https://geocode-maps.yandex.ru/1.x/"
            f"?apikey={YANDEX_GEOCODER_API_KEY}&geocode={encoded_address}&format=json&results=1"
        )
        response = requests.get(url, timeout=5)
        if response.status_code != 200:
            logger.error(f"Геокодер вернул ошибку: {response.status_code}")
            return False, "Сервис проверки адреса временно недоступен. Попробуйте позже."

        data = response.json()
        geo_objects = data.get("response", {}).get("GeoObjectCollection", {}).get("featureMember", [])
        if not geo_objects:
            return False, "Адрес не найден. Проверьте правильность ввода."

        found_address = geo_objects[0].get("GeoObject", {}).get("metaDataProperty", {}).get(
            "GeocoderMetaData", {}
        ).get("text", "")
        if found_address:
            return True, found_address
        return False, "Не удалось распознать адрес."
    except Exception as e:
        logger.error(f"Ошибка геокодера: {e}")
        return False, "Ошибка при проверке адреса. Повторите ввод."
