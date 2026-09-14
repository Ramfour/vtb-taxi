import re
from datetime import datetime


def normalize_phone(phone: str) -> str:
    """
    Приводит номер телефона к формату +7XXXXXXXXXX.
    Примеры: 89131234567 -> +79131234567
              9123456789 -> +79123456789
              +7(912)345-67-89 -> +79123456789
    """
    digits = re.sub(r"\D", "", phone)

    if len(digits) == 11 and digits.startswith("8"):
        return f"+7{digits[1:]}"
    if len(digits) == 11 and digits.startswith("7"):
        return f"+{digits}"
    if len(digits) == 10:
        return f"+7{digits}"
    return phone


ORDER_FORBIDDEN_START = datetime.strptime("06:00", "%H:%M").time()
ORDER_FORBIDDEN_END = datetime.strptime("22:00", "%H:%M").time()


def is_forbidden_order_time(order_time):
    return ORDER_FORBIDDEN_START < order_time < ORDER_FORBIDDEN_END
