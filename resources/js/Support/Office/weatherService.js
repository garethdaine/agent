const DEFAULT_LOCATION = { lat: 51.5074, lon: -0.1278 };
const POLL_INTERVAL = 600_000;

export function getLocation() {
    return new Promise((resolve) => {
        if (!('geolocation' in navigator)) {
            resolve(DEFAULT_LOCATION);
            return;
        }
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ lat: pos.coords.latitude, lon: pos.coords.longitude }),
            () => resolve(DEFAULT_LOCATION),
            { enableHighAccuracy: false, timeout: 8000, maximumAge: 300_000 },
        );
    });
}

export async function fetchWeather(lat, lon) {
    try {
        const url = `https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,weather_code,is_day,wind_speed_10m`;
        const res = await fetch(url);
        if (!res.ok) return null;
        const data = await res.json();
        const c = data.current;
        return {
            weatherCode: c.weather_code,
            isDay: c.is_day === 1,
            temperature: c.temperature_2m,
            windSpeed: c.wind_speed_10m,
        };
    } catch {
        return null;
    }
}

export function classifyWeather(code) {
    if (code === 0) return 'clear';
    if (code <= 3) return 'cloudy';
    if (code <= 48) return 'fog';
    if (code <= 67) return 'rain';
    if (code <= 77) return 'snow';
    if (code <= 86) return 'showers';
    if (code <= 99) return 'thunderstorm';
    return 'clear';
}

export function startWeatherPolling(callback) {
    let timer = null;
    let location = null;

    async function poll() {
        if (!location) location = await getLocation();
        const weather = await fetchWeather(location.lat, location.lon);
        if (weather) callback(weather);
    }

    poll();
    timer = setInterval(poll, POLL_INTERVAL);

    return () => {
        if (timer) clearInterval(timer);
    };
}
