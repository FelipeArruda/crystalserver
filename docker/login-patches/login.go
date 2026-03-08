package api

import (
	"context"
	"database/sql"
	"net/http"
	"strconv"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/opentibiabr/login-server/src/api/models"
	"github.com/opentibiabr/login-server/src/database"
	"github.com/opentibiabr/login-server/src/grpc/login_proto_messages"
)

func (_api *Api) login(c *gin.Context) {
	payload := buildPayloadFromRequest(c)

	switch payload.Type {
	case "eventschedule":
		database.HandleEventSchedule(c, _api.CorePath+"XML/events.xml")
	case "boostedcreature":
		database.HandleBoostedCreature(c, _api.DB, &_api.BoostedCreatureID, &_api.BoostedBossID)
	case "login":
		// Some custom clients still send account name instead of email.
		if payload.Email != "" && !strings.Contains(payload.Email, "@") {
			if resolvedEmail := resolveEmailByAccountName(_api.DB, payload.Email); resolvedEmail != "" {
				payload.Email = resolvedEmail
			}
		}

		grpcClient := login_proto_messages.NewLoginServiceClient(_api.GrpcConnection)

		res, err := grpcClient.Login(
			context.Background(),
			&login_proto_messages.LoginRequest{Email: payload.Email, Password: payload.Password},
		)

		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}

		if res.GetError() != nil {
			c.JSON(http.StatusOK, buildErrorPayloadFromMessage(res))
			return
		}

		c.JSON(http.StatusOK, buildPayloadFromMessage(res))
	default:
		c.JSON(http.StatusNotImplemented, gin.H{"status": "not implemented"})
	}
}

func buildPayloadFromRequest(c *gin.Context) models.RequestPayload {
	payload := models.RequestPayload{}

	if strings.EqualFold(c.Request.Method, http.MethodPost) &&
		strings.Contains(strings.ToLower(c.GetHeader("Content-Type")), "application/json") {
		_ = c.ShouldBindJSON(&payload)
	}

	payload.Email = firstNonEmpty(payload.Email,
		c.Query("email"), c.Query("account"), c.Query("accountname"), c.Query("name"),
		c.PostForm("email"), c.PostForm("account"), c.PostForm("accountname"), c.PostForm("name"),
	)
	payload.Password = firstNonEmpty(payload.Password,
		c.Query("password"), c.Query("pass"),
		c.PostForm("password"), c.PostForm("pass"),
	)
	payload.Type = strings.ToLower(firstNonEmpty(payload.Type,
		c.Query("type"),
		c.PostForm("type"),
	))
	if payload.Type == "" {
		payload.Type = "login"
	}

	if !payload.StayLoggedIn {
		payload.StayLoggedIn = toBool(firstNonEmpty(
			c.Query("stayloggedin"),
			c.PostForm("stayloggedin"),
		))
	}

	return payload
}

func firstNonEmpty(values ...string) string {
	for _, value := range values {
		if strings.TrimSpace(value) != "" {
			return value
		}
	}
	return ""
}

func toBool(value string) bool {
	if value == "" {
		return false
	}
	parsed, err := strconv.ParseBool(value)
	if err != nil {
		return value == "1"
	}
	return parsed
}

func resolveEmailByAccountName(db *sql.DB, accountName string) string {
	if db == nil || strings.TrimSpace(accountName) == "" {
		return ""
	}

	var email string
	err := db.QueryRow("SELECT email FROM accounts WHERE name = ? LIMIT 1", accountName).Scan(&email)
	if err != nil {
		return ""
	}

	return strings.TrimSpace(email)
}

func buildPayloadFromMessage(msg *login_proto_messages.LoginResponse) models.ResponsePayload {
	return models.ResponsePayload{
		PlayData: models.PlayData{
			Worlds:     models.LoadWorldsFromMessage(msg.PlayData.Worlds),
			Characters: models.LoadCharactersFromMessage(msg.PlayData.Characters),
		},
		Session: models.LoadSessionFromMessage(msg.GetSession()),
	}
}

func buildErrorPayloadFromMessage(msg *login_proto_messages.LoginResponse) models.LoginErrorPayload {
	return models.LoginErrorPayload{
		ErrorCode:    int(msg.Error.Code),
		ErrorMessage: msg.Error.Message,
	}
}
