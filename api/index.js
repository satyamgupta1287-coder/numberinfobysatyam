import puppeteer from 'puppeteer-core';
import chromium from '@sparticuz/chromium';

export default async function handler(req, res) {
    // CORS headers allow karne ke liye
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Content-Type', 'application/json');

    const API_KEY = 'satyam';
    const apikey = req.query.apikey || '';

    if (apikey !== API_KEY) {
        return res.status(401).json({
            success: false,
            message: "Invalid API Key",
            developer: "https://t.me/satyamgupta9999",
            credit: "https://t.me/satyamgupta9999",
            private: "https://t.me/osintbysatyam"
        });
    }

    let number = req.query.number || '';
    number = number.replace(/\D/g, ''); // Sirf digits allow karein

    if (!number) {
        return res.status(400).json({
            success: false,
            message: "Please provide number",
            example: "?apikey=satyam&number=9570187989",
            developer: "https://t.me/satyamgupta9999",
            credit: "https://t.me/osintsatyam",
            private: "https://t.me/osintbysatyam"
        });
    }

    const targetUrl = `http://num-info-advance-shadow-hex.site.je/?api_key=fuckyou&mobile=${number}`;
    let browser = null;

    try {
        // Vercel par invisible browser (Chromium) launch karna
        browser = await puppeteer.launch({
            args: chromium.args,
            defaultViewport: chromium.defaultViewport,
            executablePath: await chromium.executablePath(),
            headless: chromium.headless,
            ignoreHTTPSErrors: true,
        });

        const page = await browser.newPage();
        
        // Asli browser dikhne ke liye User-Agent set karna
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

        // Website par jana aur JS/Network requests complete hone ka wait karna
        await page.goto(targetUrl, { waitUntil: 'networkidle2', timeout: 20000 });
        
        // JS challenge pass hone ke liye extra 2 second wait (safe side)
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Page par jo bhi text (JSON) aaya hai use extract karna
        const content = await page.evaluate(() => document.body.innerText);
        
        let data;
        try {
            data = JSON.parse(content);
        } catch (e) {
            throw new Error("Target server se JSON nahi mila. Ho sakta hai JS challenge fail ho gaya ho.");
        }

        if (!data || !data.data) {
            throw new Error("Invalid response or JS challenge failed");
        }

        // ==========================================
        // DATA CLEANUP & SWAPPING LOGIC
        // ==========================================
        let cleanData = data.data;

        // 1. Father name aur Full name ko swap karna
        if (cleanData.personal_info) {
            const wrongFullName = cleanData.personal_info.full_name || '';
            const wrongFatherName = cleanData.personal_info.father_name || '';

            cleanData.personal_info.full_name = wrongFatherName;
            cleanData.personal_info.father_name = wrongFullName;
            
            // Faltu Developer tag remove karna
            if (cleanData.personal_info.Developer) {
                delete cleanData.personal_info.Developer;
            }
        }

        // 2. Baki jagah se faltu tags hatana
        if (cleanData.contact_info && cleanData.contact_info.Developer) {
            delete cleanData.contact_info.Developer;
        }
        if (cleanData.other_info && cleanData.other_info.Developer) {
            delete cleanData.other_info.Developer;
        }
        if (cleanData.Developer) {
            delete cleanData.Developer;
        }

        // Sab successful hone par clean data return karna
        return res.status(200).json({
            success: true,
            developer: "Satyam Gupta",
            credit: "https://t.me/osintbysatyam",
            private: "https://t.me/+14rDlunTEzwwZGY1",
            result: cleanData
        });

    } catch (error) {
        // Agar kuch fail hota hai toh error message bhejna
        return res.status(500).json({
            success: false,
            message: "Failed to fetch data",
            debug_error: error.message,
            developer: "https://t.me/osintbysatyam",
            credit: "satyamgupta",
            private: "https://t.me/osintbysatyam"
        });
    } finally {
        // Browser zaroor close karein taaki Vercel resources freeze na hon
        if (browser) {
            await browser.close();
        }
    }
}
