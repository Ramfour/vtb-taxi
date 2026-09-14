import logging
from typing import Any

import httpx

from config import BOT_API_SECRET, LARAVEL_API_URL

logger = logging.getLogger(__name__)

_client: httpx.AsyncClient | None = None


def _get_client() -> httpx.AsyncClient:
    global _client
    if _client is None or _client.is_closed:
        _client = httpx.AsyncClient(
            base_url=LARAVEL_API_URL,
            headers={
                "X-Bot-Token": BOT_API_SECRET,
                "Accept": "application/json",
                "Content-Type": "application/json",
            },
            timeout=30.0,
        )
    return _client


def _telegram_headers(telegram_id: str | int) -> dict:
    return {"X-Telegram-Id": str(telegram_id)}


class ApiError(Exception):
    def __init__(self, status_code: int, message: str):
        self.status_code = status_code
        self.message = message
        super().__init__(f"API error {status_code}: {message}")


def _raise_for(response: httpx.Response) -> None:
    if response.is_error:
        try:
            body = response.json()
            message = body.get("message") or body.get("error") or response.text
        except Exception:
            message = response.text
        raise ApiError(response.status_code, message)


async def link_telegram(employee_number: str, telegram_id: str | int) -> dict[str, Any]:
    client = _get_client()
    response = await client.post(
        "/api/bot/auth/link",
        json={"employee_number": employee_number, "telegram_id": str(telegram_id)},
    )
    _raise_for(response)
    return response.json()


async def get_me(telegram_id: str | int) -> dict[str, Any]:
    client = _get_client()
    response = await client.get(
        "/api/bot/me",
        headers=_telegram_headers(telegram_id),
    )
    _raise_for(response)
    return response.json()


async def unlink_telegram(telegram_id: str | int) -> None:
    client = _get_client()
    response = await client.post(
        "/api/bot/auth/unlink",
        headers=_telegram_headers(telegram_id),
    )
    _raise_for(response)


async def get_my_requests(telegram_id: str | int) -> dict[str, Any]:
    client = _get_client()
    response = await client.get(
        "/api/bot/employee/requests",
        headers=_telegram_headers(telegram_id),
    )
    _raise_for(response)
    return response.json()


async def create_request(
    telegram_id: str | int,
    full_name: str,
    phone: str,
    address_raw: str,
    date_time: str,
) -> dict[str, Any]:
    client = _get_client()
    response = await client.post(
        "/api/bot/employee/requests",
        headers=_telegram_headers(telegram_id),
        json={
            "full_name": full_name,
            "phone": phone,
            "address_raw": address_raw,
            "date_time": date_time,
        },
    )
    _raise_for(response)
    return response.json()


async def cancel_request(telegram_id: str | int, temp_request_id: int, reason: str = "") -> dict[str, Any]:
    client = _get_client()
    response = await client.patch(
        f"/api/bot/employee/requests/{temp_request_id}/cancel",
        headers=_telegram_headers(telegram_id),
        json={"reason": reason},
    )
    _raise_for(response)
    return response.json()


async def get_pending_requests(telegram_id: str | int) -> dict[str, Any]:
    client = _get_client()
    response = await client.get(
        "/api/bot/manager/requests",
        headers=_telegram_headers(telegram_id),
    )
    _raise_for(response)
    return response.json()


async def review_request(
    telegram_id: str | int,
    temp_request_id: int,
    action: str,
    manager_comment: str = "",
    rejection_reason: str = "",
) -> dict[str, Any]:
    client = _get_client()
    response = await client.patch(
        f"/api/bot/manager/requests/{temp_request_id}/review",
        headers=_telegram_headers(telegram_id),
        json={
            "action": action,
            "manager_comment": manager_comment or None,
            "rejection_reason": rejection_reason or None,
        },
    )
    _raise_for(response)
    return response.json()


async def finalize_requests(telegram_id: str | int) -> dict[str, Any]:
    client = _get_client()
    response = await client.post(
        "/api/bot/manager/requests/finalize",
        headers=_telegram_headers(telegram_id),
        json={},
    )
    _raise_for(response)
    return response.json()


def is_configured() -> bool:
    return bool(LARAVEL_API_URL and BOT_API_SECRET)
