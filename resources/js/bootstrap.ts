import axios from "axios";
import Cookies from "js-cookie";
import { getLocalTimeZone } from "@internationalized/date";

window.axios = axios;
window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

Cookies.set("tz", getLocalTimeZone(), {
	expires: new Date(Date.now() + 1000 * 60 * 60 * 24 * 365 * 10),
	sameSite: "Lax"
});
